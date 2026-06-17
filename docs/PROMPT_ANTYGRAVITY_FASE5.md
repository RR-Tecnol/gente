# PROMPT ANTYGRAVITY — FASE 5 GENTE v3 (Correções EsocialXmlService)

> **Cole este prompt no Antygravity APENAS após Fase 3 ter sido auditada e aprovada por Claude.**
> Estimativa total: ~1h30 Antygravity (auditoria Claude separada: ~40min).
> Pré-condição: Fases 1 + 2-A + 2-B + fix GAP-MF-04 + Fase 3 mergeadas. Branch limpa.

---

## CONTEXTO DA FASE 5

A auditoria profunda Etapa 5 (`docs/AUDITORIA_PROFUNDA_ETAPA_05_PERIFERICOS_2026-05-07.md`) identificou **6 bugs críticos** no `EsocialXmlService` que **garantem rejeição pelo governo** se enviarmos eventos eSocial em produção:

| ID | Severidade | Bug |
|----|-----------|-----|
| **R52** | 🔴 ALTO | `gerarS1200` faz `where('FUNCIONARIO_ID')` na tabela `FOLHA` (não existe — está em DETALHE_FOLHA) + `sum('FOLHA_BRUTO')` (campo errado, é `DETALHE_FOLHA_PROVENTOS`). Método sempre retorna 0. |
| **R53** | 🔴 ALTO | `tpAmb=1` (PRODUÇÃO governo) hardcoded em 4 métodos. Roda em testes contra ambiente real. |
| **R54** | 🔴 ALTO | S-2200 envia `sexo=M`, `racaCor=1`, `estCiv=1`, endereço "Rua Nao Informado", CEP `65000000`, salário `1412.00` hardcoded para todos. eSocial rejeita. |
| **R55** | 🔴 ALTO | `perApur` recebe `FUNCIONARIO_DATA_INICIO` (AAAA-MM-DD) onde schema espera AAAA-MM. Erro de validação garantido. |
| **R59** | 🟡 MÉDIO | CNPJ `'06205244000149'` hardcoded em 4 lugares. Impede multi-município. |
| **R60** | 🟡 MÉDIO | `indRetif=1` (original) hardcoded — re-emissão impossível (gera duplicidade). |

**BÔNUS descoberto pela auditoria pré-Fase 5:** `getFuncionarioDados()` faz `'p.PESSOA_NASCIMENTO'` mas o schema real é `'p.PESSOA_DATA_NASCIMENTO'`. Outro bug — vamos tratar como **R52-B** (mesmo PR).

### Estratégia — refatoração arquitetural mínima

Como `EsocialXmlService` é **protótipo de demonstração**, a refatoração é grande mas tem que ser cirúrgica para não estourar o tempo:

1. **Centralizar config em `config/esocial.php`** — CNPJ, ambiente, versionProc.
2. **Adicionar enum/helpers para mapeamento `PESSOA_GENERO`/`PESSOA_RACA`/`PESSOA_ESTADO_CIVIL`** (integers TABELA_GENERICA → códigos eSocial).
3. **Corrigir 6 bugs em sequência.**
4. **Não testar contra ambiente real do governo** — apenas validar XML estruturalmente via leitura textual + smoke `php -l`.

### Princípios de design

1. **Sem testes contra governo.** Fase 5 valida apenas: (a) sintaxe PHP, (b) presença das correções, (c) ausência dos hardcodes.
2. **`config/esocial.php` é a fonte única** para CNPJ, ambiente, versão. Seeders/configs externos preenchem.
3. **Schema de PESSOA confirmado por Claude via MCP:**
   - `PESSOA_GENERO` (integer → mapear via TABELA_GENERICA)
   - `PESSOA_RACA` (integer → idem)
   - `PESSOA_ESTADO_CIVIL` (integer → idem)
   - `PESSOA_DATA_NASCIMENTO` (date)
   - `PESSOA_ENDERECO` (string 300), `PESSOA_CEP` (string 20), `BAIRRO_ID`, `CIDADE_ID` (FKs)
   - `PESSOA_ESCOLARIDADE` (integer → mapear)
   - **NÃO TEM `PIS_PASEP` no schema atual** — usar fallback `?? ''`.
4. **NÃO mexer em routes/web.php nem em routes/folha.php.**

---

## REGRAS CRÍTICAS DE EXECUÇÃO

1. **Trabalhar em ordem:** T5.1 → T5.2 → T5.3 → T5.4 → T5.5. Cada um depende do anterior.
2. **Um commit por tarefa** (facilita rollback).
3. **Validar cada tarefa** com Select-String + `php -l` antes de seguir.
4. **Se algum trecho não bater** com o esperado, **PARAR e reportar**.
5. **Tinker/artisan dinâmico não é exigido** (PHP 8.1 local). Validação textual + auditoria Claude via MCP.

---

## T5.1 — Estender `config/esocial.php` com configuração centralizada (~10 min)

**Por que primeiro:** os outros tasks dependem de ler `config('esocial.cnpj_empregador')`, `config('esocial.ambiente')`, etc. Sem isso, vai ter que hardcodar de novo.

**Arquivo:** `config/esocial.php`

**Conteúdo atual:**

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | eSocial Queue Resilience (P2/P5-standby)
    |--------------------------------------------------------------------------
    */
    'max_retry' => (int) env('ESOCIAL_MAX_RETRY', 5),
];
```

**Conteúdo corrigido:**

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | eSocial Queue Resilience (P2/P5-standby)
    |--------------------------------------------------------------------------
    */
    'max_retry' => (int) env('ESOCIAL_MAX_RETRY', 5),

    /*
    |--------------------------------------------------------------------------
    | Empregador (CNPJ raiz da prefeitura)
    |--------------------------------------------------------------------------
    | Substitui hardcode em EsocialXmlService (R59).
    | Para multi-município (P6), passar via tenant context — CNPJ aqui é fallback.
    */
    'cnpj_empregador' => env('ESOCIAL_CNPJ_EMPREGADOR', '06205244000149'), // PMSL/MA default
    'tipo_inscricao' => env('ESOCIAL_TIPO_INSCRICAO', '1'), // 1=CNPJ, 2=CPF

    /*
    |--------------------------------------------------------------------------
    | Ambiente do eSocial
    |--------------------------------------------------------------------------
    | 1 = Produção (vai para o governo de verdade)
    | 2 = Produção restrita (homologação oficial do governo)
    |
    | DEFAULT é 2 (homologação). Para enviar pra produção real, configurar
    | ESOCIAL_AMBIENTE=1 no .env DA PRODUÇÃO. Em dev/staging, sempre 2.
    | Substitui hardcode em EsocialXmlService (R53).
    */
    'ambiente' => (int) env('ESOCIAL_AMBIENTE', 2),

    /*
    |--------------------------------------------------------------------------
    | Versão do processador emissor (verProc)
    |--------------------------------------------------------------------------
    */
    'versao_proc' => env('ESOCIAL_VERSAO_PROC', 'GENTE-v3'),

    /*
    |--------------------------------------------------------------------------
    | Mapeamento de domínios PESSOA → códigos eSocial
    |--------------------------------------------------------------------------
    | Os campos `PESSOA_GENERO`, `PESSOA_RACA`, `PESSOA_ESTADO_CIVIL`,
    | `PESSOA_ESCOLARIDADE` em GENTE são integers que apontam para TABELA_GENERICA.
    | O eSocial usa códigos próprios. Este mapeamento traduz.
    |
    | Documentação eSocial: Tabela 02 (raça/cor), Tabela 17 (estado civil),
    | Tabela 18 (grau instrução).
    */
    'mapeamento' => [
        // PESSOA_GENERO → eSocial sexo (M/F)
        'sexo' => [
            1 => 'M', // Masculino
            2 => 'F', // Feminino
        ],
        // PESSOA_RACA → eSocial racaCor (1-6 + 9)
        'raca_cor' => [
            1 => '1', // Branca
            2 => '2', // Preta
            3 => '3', // Parda
            4 => '4', // Amarela
            5 => '5', // Indígena
            6 => '6', // Não informado
        ],
        // PESSOA_ESTADO_CIVIL → eSocial estCiv (1-5)
        'estado_civil' => [
            1 => '1', // Solteiro
            2 => '2', // Casado
            3 => '3', // Divorciado
            4 => '4', // Separado
            5 => '5', // Viúvo
        ],
        // PESSOA_ESCOLARIDADE → eSocial grauInstr (01-12)
        'grau_instrucao' => [
            1 => '01',  // Analfabeto
            2 => '02',  // Até 5ª incompleto
            3 => '03',  // 5ª completo fundamental
            4 => '04',  // 6ª a 9ª fundamental
            5 => '05',  // Fundamental completo
            6 => '06',  // Médio incompleto
            7 => '07',  // Médio completo
            8 => '08',  // Superior incompleto
            9 => '09',  // Superior completo
            10 => '10', // Pós-graduação
            11 => '11', // Mestrado
            12 => '12', // Doutorado
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Indicador de retificação default
    |--------------------------------------------------------------------------
    | 1 = Original (primeiro envio)
    | 2 = Retificação (correção de evento já enviado — exige campo nrRecibo)
    | 3 = Exclusão de evento já enviado
    | Substitui hardcode em EsocialXmlService (R60).
    */
    'ind_retif_default' => (int) env('ESOCIAL_IND_RETIF_DEFAULT', 1),
];
```

**Validação:**

```powershell
php -l config/esocial.php
```
Esperado: `No syntax errors detected`.

```powershell
Select-String -Path "config/esocial.php" -Pattern "cnpj_empregador|ambiente|versao_proc|mapeamento|ind_retif_default"
```
Esperado: 5+ ocorrências (cada chave aparece pelo menos 1 vez).

**Commit:** `feat(Fase5-T1): config/esocial.php com cnpj/ambiente/mapeamento centralizados`

---

## T5.2 — Corrigir `getFuncionarioDados` para schema real (~10 min)

**Por que segundo:** todos os métodos de geração XML dependem desse helper. Corrigir aqui antes de mexer nos métodos individuais.

**Arquivo:** `app/Services/EsocialXmlService.php`

**Trecho atual (linhas ~16-30):**

```php
    /**
     * Helper para buscar dados básicos do funcionário
     */
    private function getFuncionarioDados(int $funcionarioId)
    {
        $func = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcionarioId)
            ->select('f.*', 'p.PESSOA_NOME', 'p.PESSOA_CPF_NUMERO', 'p.PESSOA_NASCIMENTO', 'p.PIS_PASEP', 'c.CARGO_NOME', 'c.CBO')
            ->first();

        if (!$func) {
            throw new \Exception("Funcionário $funcionarioId não encontrado.");
        }
        return $func;
    }
```

**Trecho corrigido:**

```php
    /**
     * Helper para buscar dados básicos do funcionário.
     *
     * Schema real (auditado por Claude via MCP):
     *   - PESSOA tem PESSOA_DATA_NASCIMENTO (não PESSOA_NASCIMENTO)
     *   - PESSOA tem PESSOA_GENERO/PESSOA_RACA/PESSOA_ESTADO_CIVIL/PESSOA_ESCOLARIDADE (integers → mapear)
     *   - PESSOA tem PESSOA_ENDERECO/PESSOA_CEP/BAIRRO_ID/CIDADE_ID
     *   - PESSOA NÃO TEM PIS_PASEP no schema atual (Schema::hasColumn check defensivo)
     */
    private function getFuncionarioDados(int $funcionarioId)
    {
        $temPisPasep = \Illuminate\Support\Facades\Schema::hasColumn('PESSOA', 'PIS_PASEP');

        $cols = [
            'f.*',
            'p.PESSOA_NOME',
            'p.PESSOA_CPF_NUMERO',
            'p.PESSOA_DATA_NASCIMENTO',
            'p.PESSOA_GENERO',
            'p.PESSOA_RACA',
            'p.PESSOA_ESTADO_CIVIL',
            'p.PESSOA_ESCOLARIDADE',
            'p.PESSOA_ENDERECO',
            'p.PESSOA_CEP',
            'p.BAIRRO_ID',
            'p.CIDADE_ID',
            'c.CARGO_NOME',
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CBO')) {
            $cols[] = 'c.CBO';
        }
        if ($temPisPasep) {
            $cols[] = 'p.PIS_PASEP';
        }

        $func = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcionarioId)
            ->select($cols)
            ->first();

        if (!$func) {
            throw new \Exception("Funcionário $funcionarioId não encontrado.");
        }

        // Compat: se PIS_PASEP não existe, garantir property null (evitar undefined property warning)
        if (!$temPisPasep) {
            $func->PIS_PASEP = null;
        }

        return $func;
    }

    /**
     * Helper para buscar bairro, cidade e UF de uma PESSOA — necessário em S-2200 (endereço).
     * Schema-tolerante: se BAIRRO/CIDADE/UF não existirem, retorna defaults.
     *
     * @return array{bairro: string, cidade: string, uf: string, cod_munic: ?string}
     */
    private function getEnderecoExpandido($func): array
    {
        $bairro = 'Centro';
        $cidade = 'São Luís';
        $uf = 'MA';
        $codMunic = '2111300'; // IBGE São Luís default

        if ($func->BAIRRO_ID && \Illuminate\Support\Facades\Schema::hasTable('BAIRRO')) {
            $b = DB::table('BAIRRO')->where('BAIRRO_ID', $func->BAIRRO_ID)->first();
            if ($b && isset($b->BAIRRO_NOME)) {
                $bairro = (string) $b->BAIRRO_NOME;
            }
        }

        if ($func->CIDADE_ID && \Illuminate\Support\Facades\Schema::hasTable('CIDADE')) {
            $c = DB::table('CIDADE')
                ->leftJoin('UF', 'UF.UF_ID', '=', 'CIDADE.UF_ID')
                ->where('CIDADE.CIDADE_ID', $func->CIDADE_ID)
                ->select('CIDADE.CIDADE_NOME', 'UF.UF_SIGLA', 'CIDADE.CIDADE_CODIGO_IBGE')
                ->first();
            if ($c) {
                $cidade = (string) ($c->CIDADE_NOME ?? $cidade);
                $uf = (string) ($c->UF_SIGLA ?? $uf);
                $codMunic = $c->CIDADE_CODIGO_IBGE ? (string) $c->CIDADE_CODIGO_IBGE : $codMunic;
            }
        }

        return ['bairro' => $bairro, 'cidade' => $cidade, 'uf' => $uf, 'cod_munic' => $codMunic];
    }

    /**
     * Mapeia integer de domínio (PESSOA_GENERO/RACA/EST_CIVIL/ESCOLARIDADE) para código eSocial.
     */
    private function mapearDominio(string $tipo, $valor, string $default): string
    {
        if ($valor === null) {
            return $default;
        }
        $map = config('esocial.mapeamento.' . $tipo, []);
        $key = (int) $valor;

        return (string) ($map[$key] ?? $default);
    }
```

**Mudanças exatas:**
- (a) `PESSOA_NASCIMENTO` → `PESSOA_DATA_NASCIMENTO`
- (b) Adicionadas 6 colunas de mapeamento (`GENERO`, `RACA`, `ESTADO_CIVIL`, `ESCOLARIDADE`, `ENDERECO`, `CEP`)
- (c) Adicionados 2 FKs (`BAIRRO_ID`, `CIDADE_ID`)
- (d) `PIS_PASEP` agora é defensivo via `Schema::hasColumn`
- (e) `CBO` também (não estava no schema base)
- (f) Adicionados 2 helpers privados: `getEnderecoExpandido()` e `mapearDominio()` — usados em T5.4 (S-2200).

**Validação:**

```powershell
php -l app/Services/EsocialXmlService.php
```

```powershell
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "PESSOA_NASCIMENTO[^_]" -SimpleMatch
```
Saída esperada: 0 ocorrências (confirma que a referência errada foi removida).

```powershell
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "PESSOA_DATA_NASCIMENTO|getEnderecoExpandido|mapearDominio"
```
Saída esperada: 4+ ocorrências.

**Commit:** `fix(Fase5-T2,R52-B): EsocialXmlService::getFuncionarioDados usa schema real + helpers endereço/mapeamento`

---

## T5.3 — Refatorar `gerarS1200` (corrige R52 + R53 + R55 + R59 + R60) (~20 min)

**Bugs alvo:**
- **R52** — query usa `where('FUNCIONARIO_ID')` na tabela `FOLHA` (que NÃO tem essa coluna — está em `DETALHE_FOLHA`) + `sum('FOLHA_BRUTO')` (campo errado, é `DETALHE_FOLHA_PROVENTOS`). Método sempre retorna 0.
- **R53** — `tpAmb=1` (PRODUÇÃO governo) hardcoded.
- **R55** — `perApur` recebe `AAAA-MM-DD` (data completa) onde schema espera `AAAA-MM`.
- **R59** — CNPJ hardcoded.
- **R60** — `indRetif=1` hardcoded.

**Arquivo:** `app/Services/EsocialXmlService.php`

**Trecho atual (método `gerarS1200`, linhas ~33-90):**

```php
    /**
     * S-1200 - Remuneração de Trabalhador
     */
    public function gerarS1200(int $funcionarioId, string $competencia): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);
        
        // Formata competência de 'Ym' ou 'Y-m' para 'YYYY-MM' (o schema de S-1200 permite YYYY-MM)
        if (strlen($competencia) === 6) {
            $perApur = substr($competencia, 0, 4) . '-' . substr($competencia, 4, 2);
        } else {
            $perApur = date('Y-m', strtotime($competencia));
        }

        // Calcula total de remuneração da competência baseada na FOLHA gerada
        $remuneracaoTotal = DB::table('FOLHA')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->where('FOLHA_COMPETENCIA', str_replace('-', '', $perApur))
            ->sum('FOLHA_BRUTO') ?? '0.00';

        $cnpj = '06205244000149';
        $idEvento = $this->gerarIdEvento('1', $cnpj, $funcionarioId);
        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        
        $codCateg = '301'; // Servidor Público Temporário/Estatutário

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtRemun/v02_01_00">
  <evtRemun Id="{$idEvento}">
    <ideEvento>
      <indRetif>1</indRetif>
      <perApur>{$perApur}</perApur>
      <indApuracao>1</indApuracao>
      <indGuia>1</indGuia>
      <tpAmb>1</tpAmb>
      <procEmi>1</procEmi>
      <verProc>GENTE-v3</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>1</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <ideVinculo>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <matricula>{$func->FUNCIONARIO_MATRICULA}</matricula>
    </ideVinculo>
    <dmDev>
      <codCateg>{$codCateg}</codCateg>
      <infoPerApur>
        <ideEstabLot>
          <remunPerApur>
            <vrTotCont>{$remuneracaoTotal}</vrTotCont>
          </remunPerApur>
        </ideEstabLot>
      </infoPerApur>
    </dmDev>
  </evtRemun>
</eSocial>
XML;
        return $xml;
    }
```

**Trecho corrigido:**

```php
    /**
     * S-1200 — Remuneração de Trabalhador.
     *
     * Correções Fase 5:
     *   - R52: query corrigida para JOIN DETALHE_FOLHA + FOLHA por FUNCIONARIO_ID + competência,
     *          campo correto DETALHE_FOLHA_PROVENTOS (não FOLHA_BRUTO).
     *   - R53: tpAmb via config('esocial.ambiente') (default 2=homologação).
     *   - R55: perApur normalizado para AAAA-MM via Carbon (não recebe AAAA-MM-DD).
     *   - R59: CNPJ via config('esocial.cnpj_empregador').
     *   - R60: indRetif via config('esocial.ind_retif_default'), permitindo retificação.
     *
     * @param  int     $funcionarioId
     * @param  string  $competencia      AAAA-MM, AAAAMM ou AAAA-MM-DD (será normalizado)
     * @param  int     $indRetif         1=original, 2=retificação, 3=exclusão
     * @param  string  $codCateg         Código de categoria do trabalhador no eSocial (default 301)
     */
    public function gerarS1200(int $funcionarioId, string $competencia, int $indRetif = 0, string $codCateg = '301'): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);

        // R55: normalizar competência para AAAA-MM (qualquer entrada → formato fixo)
        $perApur = $this->normalizarCompetenciaYm($competencia);

        // R52: query correta — DETALHE_FOLHA tem FUNCIONARIO_ID; FOLHA tem FOLHA_COMPETENCIA.
        // Soma DETALHE_FOLHA_PROVENTOS (não FOLHA_BRUTO, que não existe).
        // Schema FOLHA_COMPETENCIA é string sem hífen (AAAAMM); convertemos.
        $compSemHifen = str_replace('-', '', $perApur);
        $remuneracaoTotal = (float) DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->where('df.FUNCIONARIO_ID', $funcionarioId)
            ->where('f.FOLHA_COMPETENCIA', $compSemHifen)
            ->whereNull('df.DETALHE_FOLHA_ERRO')
            ->sum('df.DETALHE_FOLHA_PROVENTOS');

        // R59: CNPJ via config
        $cnpj = (string) config('esocial.cnpj_empregador');
        $tpInsc = (string) config('esocial.tipo_inscricao', '1');
        $idEvento = $this->gerarIdEvento($tpInsc, $cnpj, $funcionarioId);
        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');

        // R53: ambiente via config (default 2=homologação)
        $tpAmb = (int) config('esocial.ambiente', 2);
        // R60: indRetif via parâmetro ou config (default 1=original)
        $indRetif = $indRetif > 0 ? $indRetif : (int) config('esocial.ind_retif_default', 1);
        $verProc = (string) config('esocial.versao_proc', 'GENTE-v3');

        $valorFmt = number_format($remuneracaoTotal, 2, '.', '');
        $matricula = htmlspecialchars((string) ($func->FUNCIONARIO_MATRICULA ?? ''), ENT_XML1, 'UTF-8');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtRemun/v02_01_00">
  <evtRemun Id="{$idEvento}">
    <ideEvento>
      <indRetif>{$indRetif}</indRetif>
      <perApur>{$perApur}</perApur>
      <indApuracao>1</indApuracao>
      <indGuia>1</indGuia>
      <tpAmb>{$tpAmb}</tpAmb>
      <procEmi>1</procEmi>
      <verProc>{$verProc}</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>{$tpInsc}</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <ideVinculo>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <matricula>{$matricula}</matricula>
    </ideVinculo>
    <dmDev>
      <codCateg>{$codCateg}</codCateg>
      <infoPerApur>
        <ideEstabLot>
          <remunPerApur>
            <vrTotCont>{$valorFmt}</vrTotCont>
          </remunPerApur>
        </ideEstabLot>
      </infoPerApur>
    </dmDev>
  </evtRemun>
</eSocial>
XML;

        return $xml;
    }

    /**
     * Normaliza competência para AAAA-MM independente do input (R55).
     * Aceita: 'AAAA-MM', 'AAAAMM', 'AAAA-MM-DD', timestamp.
     */
    private function normalizarCompetenciaYm(string $entrada): string
    {
        $limpa = preg_replace('/\D/', '', $entrada);

        // Se tem 6+ dígitos, primeiros 4 são ano e próximos 2 são mês
        if (strlen($limpa) >= 6) {
            return substr($limpa, 0, 4) . '-' . substr($limpa, 4, 2);
        }

        // Fallback: tentar parsear via Carbon
        try {
            return \Carbon\Carbon::parse($entrada)->format('Y-m');
        } catch (\Throwable $e) {
            return now()->format('Y-m');
        }
    }
```

**Validação:**

```powershell
php -l app/Services/EsocialXmlService.php
```

```powershell
# R52: confirmar que query agora usa DETALHE_FOLHA + JOIN
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "DETALHE_FOLHA as df|join\('FOLHA as f'|DETALHE_FOLHA_PROVENTOS"
```
Esperado: 3+ ocorrências.

```powershell
# R52: confirmar que NÃO tem mais where('FUNCIONARIO_ID') na tabela FOLHA nem sum('FOLHA_BRUTO')
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "FOLHA_BRUTO"
```
Esperado: 0 ocorrências.

```powershell
# R53/R59/R60: confirmar uso de config()
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "config\('esocial\."
```
Esperado: 5+ ocorrências.

```powershell
# R55: helper de normalização presente
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "normalizarCompetenciaYm"
```
Esperado: 2+ ocorrências (definição + chamada).

**Commit:** `fix(Fase5-T3,R52-53-55-59-60): EsocialXmlService::gerarS1200 corrige query DETALHE_FOLHA + config esocial`

---

## T5.4 — Refatorar `gerarS2200` (corrige R54 + R53 + R55 + R59 + R60) (~25 min)

**Bugs alvo:**
- **R54** — sexo/racaCor/estCiv/grauInstr hardcoded; endereço "Rua Nao Informado"; CEP `65000000`; salário `1412.00` hardcoded.
- **R53** — `tpAmb=1` hardcoded.
- **R55** — `perApur` recebe data ao invés de AAAA-MM (na verdade S-2200 usa `dtAdm` que é AAAA-MM-DD, mas o trecho atual confunde nomenclatura).
- **R59** — CNPJ hardcoded.
- **R60** — `indRetif=1` hardcoded.

**Arquivo:** `app/Services/EsocialXmlService.php`

**Trecho atual (método `gerarS2200`, linhas ~92-160):**

```php
    /**
     * S-2200 - Cadastramento Inicial do Vínculo e Admissão/Ingresso de Trabalhador
     */
    public function gerarS2200(int $funcionarioId): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);
        
        $cnpj = '06205244000149';
        $idEvento = $this->gerarIdEvento('1', $cnpj, $funcionarioId);
        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        $pisLimpo = preg_replace('/\D/', '', $func->PIS_PASEP ?? '');
        
        // Minimalistic valid structure for S-2200
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAdmissao/v02_01_00">
  <evtAdmissao Id="{$idEvento}">
    <ideEvento>
      <indRetif>1</indRetif>
      <perApur>{$func->FUNCIONARIO_DATA_INICIO}</perApur>
      <indApuracao>1</indApuracao>
      <indGuia>1</indGuia>
      <tpAmb>1</tpAmb>
      <procEmi>1</procEmi>
      <verProc>GENTE-v3</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>1</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <trabalhador>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <nmTrab>{$func->PESSOA_NOME}</nmTrab>
      <sexo>M</sexo>
      <racaCor>1</racaCor>
      <estCiv>1</estCiv>
      <grauInstr>01</grauInstr>
      <dataNascimento>{$func->PESSOA_NASCIMENTO}</dataNascimento>
      <endereco>
        <brasil>
          <tpLograd>Rua</tpLograd>
          <dscLograd>Nao Informado</dscLograd>
          <nrLograd>S/N</nrLograd>
          <bairro>Centro</bairro>
          <cep>65000000</cep>
          <codMunic>2111300</codMunic>
          <uf>MA</uf>
        </brasil>
      </endereco>
      <documentos>
        <NIS>{$pisLimpo}</NIS>
      </documentos>
    </trabalhador>
    <vinculo>
      <matricula>{$func->FUNCIONARIO_MATRICULA}</matricula>
      <tpRegTrab>2</tpRegTrab>
      <tpRegPrev>2</tpRegPrev>
      <cadIni>S</cadIni>
      <infoContrato>
        <codCateg>301</codCateg>
        <remuneracao>
          <vrSalFx>1412.00</vrSalFx>
          <undSalFixo>5</undSalFixo>
        </remuneracao>
        <duracao>
          <tpContr>1</tpContr>
        </duracao>
        <localTrabalho>
          <localTrabGeral>
            <tpInsc>1</tpInsc>
            <nrInsc>{$cnpj}</nrInsc>
          </localTrabGeral>
        </localTrabalho>
      </infoContrato>
    </vinculo>
  </evtAdmissao>
</eSocial>
XML;
        return $xml;
    }
```

**Trecho corrigido:**

```php
    /**
     * S-2200 — Cadastramento Inicial do Vínculo e Admissão.
     *
     * Correções Fase 5:
     *   - R54: dados pessoais lidos de PESSOA com fallback defensivo (sexo/raça/estCiv/grauInstr via mapeamento config).
     *   - R54: endereço lido de PESSOA + JOIN BAIRRO/CIDADE/UF (não "Rua Nao Informado" hardcoded).
     *   - R54: salário lido de TABELA_SALARIAL/CARGO real (não 1412.00 hardcoded).
     *   - R53: tpAmb via config('esocial.ambiente').
     *   - R59: CNPJ via config('esocial.cnpj_empregador').
     *   - R60: indRetif via parâmetro ou config.
     *
     * Observação eSocial: S-2200 não usa `<perApur>`. Removido. Usa `<dtAdm>` dentro de `<infoRegimeTrab>`.
     *
     * @param  int     $funcionarioId
     * @param  int     $indRetif      1=original, 2=retificação, 3=exclusão (default config)
     */
    public function gerarS2200(int $funcionarioId, int $indRetif = 0): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);

        // R59: CNPJ via config
        $cnpj = (string) config('esocial.cnpj_empregador');
        $tpInsc = (string) config('esocial.tipo_inscricao', '1');
        $idEvento = $this->gerarIdEvento($tpInsc, $cnpj, $funcionarioId);

        // R53: ambiente via config
        $tpAmb = (int) config('esocial.ambiente', 2);
        // R60: indRetif via parâmetro ou config
        $indRetif = $indRetif > 0 ? $indRetif : (int) config('esocial.ind_retif_default', 1);
        $verProc = (string) config('esocial.versao_proc', 'GENTE-v3');

        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        $pisLimpo = preg_replace('/\D/', '', (string) ($func->PIS_PASEP ?? ''));

        // R54: dados pessoais via mapeamento (PESSOA_GENERO/RACA/ESTADO_CIVIL/ESCOLARIDADE → códigos eSocial)
        $sexo = $this->mapearDominio('sexo', $func->PESSOA_GENERO ?? null, 'M');
        $racaCor = $this->mapearDominio('raca_cor', $func->PESSOA_RACA ?? null, '6'); // 6 = Não informado
        $estCiv = $this->mapearDominio('estado_civil', $func->PESSOA_ESTADO_CIVIL ?? null, '1');
        $grauInstr = $this->mapearDominio('grau_instrucao', $func->PESSOA_ESCOLARIDADE ?? null, '01');

        // R54: endereço real via JOIN BAIRRO + CIDADE + UF (com fallback defensivo)
        $endereco = $this->getEnderecoExpandido($func);
        $bairro = htmlspecialchars($endereco['bairro'], ENT_XML1, 'UTF-8');
        $cidadeCodMunic = htmlspecialchars($endereco['cod_munic'] ?? '2111300', ENT_XML1, 'UTF-8');
        $uf = htmlspecialchars($endereco['uf'], ENT_XML1, 'UTF-8');

        $cep = preg_replace('/\D/', '', (string) ($func->PESSOA_CEP ?? ''));
        if (strlen($cep) !== 8) {
            $cep = '65000000'; // fallback final apenas se CEP inválido — log warning
            \Illuminate\Support\Facades\Log::warning('[EsocialXmlService] CEP inválido para funcionário', [
                'funcionario_id' => $funcionarioId,
                'cep_bruto' => $func->PESSOA_CEP ?? null,
            ]);
        }

        $enderecoBruto = trim((string) ($func->PESSOA_ENDERECO ?? ''));
        $tpLograd = 'Rua';
        $dscLograd = 'Não Informado';
        $nrLograd = 'S/N';
        if ($enderecoBruto !== '') {
            // Tentar separar tipo + descrição + número (heurística simples)
            // Ex: "Rua das Flores, 123" → tpLograd="Rua", dscLograd="das Flores", nrLograd="123"
            if (preg_match('/^(Rua|Av\.?|Avenida|Travessa|Praça|Rod\.?|Rodovia|Alameda|Estrada|Ladeira|Beco)\s+(.+?)(?:,\s*(\S+))?$/iu', $enderecoBruto, $m)) {
                $tpLograd = $m[1];
                $dscLograd = trim($m[2]);
                $nrLograd = $m[3] ?? 'S/N';
            } else {
                $dscLograd = $enderecoBruto;
            }
        }
        $tpLograd = htmlspecialchars($tpLograd, ENT_XML1, 'UTF-8');
        $dscLograd = htmlspecialchars(mb_substr($dscLograd, 0, 100), ENT_XML1, 'UTF-8');
        $nrLograd = htmlspecialchars(mb_substr($nrLograd, 0, 10), ENT_XML1, 'UTF-8');

        // R54: salário real via TABELA_SALARIAL (com fallback para CARGO_SALARIO)
        $salario = $this->resolverSalarioFunc($funcionarioId);
        $vrSalFx = number_format($salario, 2, '.', '');

        $dtAdm = $func->FUNCIONARIO_DATA_INICIO ?? now()->format('Y-m-d');
        $nome = htmlspecialchars((string) ($func->PESSOA_NOME ?? ''), ENT_XML1, 'UTF-8');
        $matricula = htmlspecialchars((string) ($func->FUNCIONARIO_MATRICULA ?? ''), ENT_XML1, 'UTF-8');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAdmissao/v02_01_00">
  <evtAdmissao Id="{$idEvento}">
    <ideEvento>
      <indRetif>{$indRetif}</indRetif>
      <tpAmb>{$tpAmb}</tpAmb>
      <procEmi>1</procEmi>
      <verProc>{$verProc}</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>{$tpInsc}</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <trabalhador>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <nmTrab>{$nome}</nmTrab>
      <sexo>{$sexo}</sexo>
      <racaCor>{$racaCor}</racaCor>
      <estCiv>{$estCiv}</estCiv>
      <grauInstr>{$grauInstr}</grauInstr>
      <dataNascimento>{$func->PESSOA_DATA_NASCIMENTO}</dataNascimento>
      <endereco>
        <brasil>
          <tpLograd>{$tpLograd}</tpLograd>
          <dscLograd>{$dscLograd}</dscLograd>
          <nrLograd>{$nrLograd}</nrLograd>
          <bairro>{$bairro}</bairro>
          <cep>{$cep}</cep>
          <codMunic>{$cidadeCodMunic}</codMunic>
          <uf>{$uf}</uf>
        </brasil>
      </endereco>
      <documentos>
        <NIS>{$pisLimpo}</NIS>
      </documentos>
    </trabalhador>
    <vinculo>
      <matricula>{$matricula}</matricula>
      <tpRegTrab>2</tpRegTrab>
      <tpRegPrev>2</tpRegPrev>
      <cadIni>S</cadIni>
      <infoRegimeTrab>
        <infoCeletista>
          <dtAdm>{$dtAdm}</dtAdm>
        </infoCeletista>
      </infoRegimeTrab>
      <infoContrato>
        <codCateg>301</codCateg>
        <remuneracao>
          <vrSalFx>{$vrSalFx}</vrSalFx>
          <undSalFixo>5</undSalFixo>
        </remuneracao>
        <duracao>
          <tpContr>1</tpContr>
        </duracao>
        <localTrabalho>
          <localTrabGeral>
            <tpInsc>{$tpInsc}</tpInsc>
            <nrInsc>{$cnpj}</nrInsc>
          </localTrabGeral>
        </localTrabalho>
      </infoContrato>
    </vinculo>
  </evtAdmissao>
</eSocial>
XML;

        return $xml;
    }

    /**
     * Resolve salário base do funcionário com fallback (TABELA_SALARIAL → CARGO_SALARIO → 0).
     */
    private function resolverSalarioFunc(int $funcionarioId): float
    {
        try {
            $row = DB::table('FUNCIONARIO as f')
                ->leftJoin('TABELA_SALARIAL as ts', function ($j) {
                    $j->on('ts.CARREIRA_ID', '=', 'f.CARREIRA_ID')
                        ->on('ts.TABELA_CLASSE', '=', 'f.FUNCIONARIO_CLASSE')
                        ->on('ts.TABELA_REFERENCIA', '=', 'f.FUNCIONARIO_REFERENCIA');
                })
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->where('f.FUNCIONARIO_ID', $funcionarioId)
                ->select('ts.TABELA_VENCIMENTO_BASE', 'c.CARGO_SALARIO')
                ->first();

            if ($row) {
                $sal = (float) ($row->TABELA_VENCIMENTO_BASE ?? 0);
                if ($sal > 0) {
                    return $sal;
                }
                return (float) ($row->CARGO_SALARIO ?? 0);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[EsocialXmlService] falha ao resolver salário', [
                'funcionario_id' => $funcionarioId,
                'erro' => $e->getMessage(),
            ]);
        }

        return 0.0;
    }
```

**Validação:**

```powershell
php -l app/Services/EsocialXmlService.php
```

```powershell
# R54: confirmar uso de mapearDominio para sexo/racaCor/estCiv/grauInstr
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "mapearDominio\('sexo|mapearDominio\('raca_cor|mapearDominio\('estado_civil|mapearDominio\('grau_instrucao"
```
Esperado: 4 ocorrências.

```powershell
# R54: confirmar que valores hardcoded foram removidos do S-2200
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "<sexo>M<|<racaCor>1<|<estCiv>1<|<grauInstr>01<|Nao Informado|<cep>65000000<|<vrSalFx>1412\.00<"
```
Esperado: 0 ocorrências (todos vinheram via variável).

```powershell
# R54: confirmar uso de getEnderecoExpandido + resolverSalarioFunc
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "getEnderecoExpandido|resolverSalarioFunc"
```
Esperado: 4+ ocorrências.

```powershell
# Confirmar que PESSOA_DATA_NASCIMENTO é usado (não PESSOA_NASCIMENTO)
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "PESSOA_NASCIMENTO[^_]" -SimpleMatch
```
Esperado: 0 ocorrências (substituído por PESSOA_DATA_NASCIMENTO).

**Commit:** `fix(Fase5-T4,R54): EsocialXmlService::gerarS2200 lê dados reais de PESSOA + endereço expandido + salário via TABELA_SALARIAL`

---

## T5.5 — Refatorar `gerarS2206` e `gerarS2299` (corrige R53 + R55 + R59 + R60) (~15 min)

**Bugs alvo nos 2 métodos:**
- **R53** — `tpAmb=1` hardcoded
- **R55** — `perApur` recebe `FUNCIONARIO_DATA_INICIO` ou `dtDesligamento` (AAAA-MM-DD) onde S-2206 não usa `<perApur>` e S-2299 deveria usar formato adequado
- **R59** — CNPJ hardcoded
- **R60** — `indRetif=1` hardcoded

### 5.5.A — `gerarS2206` (Alteração de Contrato)

**Arquivo:** `app/Services/EsocialXmlService.php`

**Trecho atual (método `gerarS2206`, linhas ~165-205):**

```php
    /**
     * S-2206 - Alteração de Contrato de Trabalho/Vínculo
     */
    public function gerarS2206(int $funcionarioId): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);
        
        $cnpj = '06205244000149';
        $idEvento = $this->gerarIdEvento('1', $cnpj, $funcionarioId);
        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAltContratual/v02_01_00">
  <evtAltContratual Id="{$idEvento}">
    <ideEvento>
      <indRetif>1</indRetif>
      <perApur>{$func->FUNCIONARIO_DATA_INICIO}</perApur>
      <indApuracao>1</indApuracao>
      <indGuia>1</indGuia>
      <tpAmb>1</tpAmb>
      <procEmi>1</procEmi>
      <verProc>GENTE-v3</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>1</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <ideVinculo>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <matricula>{$func->FUNCIONARIO_MATRICULA}</matricula>
    </ideVinculo>
    <altContratual>
      <dtAlteracao>{$func->FUNCIONARIO_DATA_INICIO}</dtAlteracao>
      <infoCargo>
        <codCargo>{$func->CARGO_ID}</codCargo>
      </infoCargo>
    </altContratual>
  </evtAltContratual>
</eSocial>
XML;
        return $xml;
    }
```

**Trecho corrigido:**

```php
    /**
     * S-2206 — Alteração de Contrato de Trabalho/Vínculo.
     *
     * Correções Fase 5:
     *   - R53: tpAmb via config.
     *   - R55: removido <perApur> (S-2206 não usa); <dtAlteracao> recebe ?string $dtAlteracao parâmetro.
     *   - R59: CNPJ via config.
     *   - R60: indRetif via parâmetro ou config.
     *
     * @param  int          $funcionarioId
     * @param  string|null  $dtAlteracao  AAAA-MM-DD (default: hoje, fallback: FUNCIONARIO_DATA_INICIO)
     * @param  int          $indRetif     1=original, 2=retificação, 3=exclusão
     */
    public function gerarS2206(int $funcionarioId, ?string $dtAlteracao = null, int $indRetif = 0): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);

        // R59: CNPJ via config
        $cnpj = (string) config('esocial.cnpj_empregador');
        $tpInsc = (string) config('esocial.tipo_inscricao', '1');
        $idEvento = $this->gerarIdEvento($tpInsc, $cnpj, $funcionarioId);

        // R53: ambiente via config
        $tpAmb = (int) config('esocial.ambiente', 2);
        // R60: indRetif via parâmetro ou config
        $indRetif = $indRetif > 0 ? $indRetif : (int) config('esocial.ind_retif_default', 1);
        $verProc = (string) config('esocial.versao_proc', 'GENTE-v3');

        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        $matricula = htmlspecialchars((string) ($func->FUNCIONARIO_MATRICULA ?? ''), ENT_XML1, 'UTF-8');

        // R55: dtAlteracao parâmetro com fallback (não recebe FUNCIONARIO_DATA_INICIO automaticamente)
        $dtAlt = $dtAlteracao ?: ($func->FUNCIONARIO_DATA_INICIO ?? now()->format('Y-m-d'));
        $codCargo = (int) ($func->CARGO_ID ?? 0);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAltContratual/v02_01_00">
  <evtAltContratual Id="{$idEvento}">
    <ideEvento>
      <indRetif>{$indRetif}</indRetif>
      <tpAmb>{$tpAmb}</tpAmb>
      <procEmi>1</procEmi>
      <verProc>{$verProc}</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>{$tpInsc}</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <ideVinculo>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <matricula>{$matricula}</matricula>
    </ideVinculo>
    <altContratual>
      <dtAlteracao>{$dtAlt}</dtAlteracao>
      <infoCargo>
        <codCargo>{$codCargo}</codCargo>
      </infoCargo>
    </altContratual>
  </evtAltContratual>
</eSocial>
XML;

        return $xml;
    }
```

### 5.5.B — `gerarS2299` (Desligamento)

**Trecho atual (método `gerarS2299`, linhas ~210-250):**

```php
    /**
     * S-2299 - Desligamento
     */
    public function gerarS2299(int $funcionarioId, string $dataDesligamento = null): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);
        $dtDesligamento = $dataDesligamento ?? $func->FUNCIONARIO_DATA_FIM ?? now()->format('Y-m-d');
        
        $cnpj = '06205244000149';
        $idEvento = $this->gerarIdEvento('1', $cnpj, $funcionarioId);
        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtDeslig/v02_01_00">
  <evtDeslig Id="{$idEvento}">
    <ideEvento>
      <indRetif>1</indRetif>
      <perApur>{$dtDesligamento}</perApur>
      <indApuracao>1</indApuracao>
      <indGuia>1</indGuia>
      <tpAmb>1</tpAmb>
      <procEmi>1</procEmi>
      <verProc>GENTE-v3</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>1</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <ideVinculo>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <matricula>{$func->FUNCIONARIO_MATRICULA}</matricula>
    </ideVinculo>
    <infoDeslig>
      <mtvDeslig>02</mtvDeslig>
      <dtDeslig>{$dtDesligamento}</dtDeslig>
      <indPagtoAPI>N</indPagtoAPI>
    </infoDeslig>
  </evtDeslig>
</eSocial>
XML;
        return $xml;
    }
```

**Trecho corrigido:**

```php
    /**
     * S-2299 — Desligamento.
     *
     * Correções Fase 5:
     *   - R53: tpAmb via config.
     *   - R55: removido <perApur> (S-2299 não usa); <dtDeslig> formato AAAA-MM-DD.
     *   - R59: CNPJ via config.
     *   - R60: indRetif via parâmetro ou config.
     *
     * @param  int          $funcionarioId
     * @param  string|null  $dataDesligamento  AAAA-MM-DD (default: FUNCIONARIO_DATA_FIM ou hoje)
     * @param  string       $mtvDeslig         Motivo do desligamento (default 02 = exoneração)
     * @param  int          $indRetif          1=original, 2=retificação, 3=exclusão
     */
    public function gerarS2299(int $funcionarioId, ?string $dataDesligamento = null, string $mtvDeslig = '02', int $indRetif = 0): string
    {
        $func = $this->getFuncionarioDados($funcionarioId);
        $dtDesligamento = $dataDesligamento ?? $func->FUNCIONARIO_DATA_FIM ?? now()->format('Y-m-d');

        // R59: CNPJ via config
        $cnpj = (string) config('esocial.cnpj_empregador');
        $tpInsc = (string) config('esocial.tipo_inscricao', '1');
        $idEvento = $this->gerarIdEvento($tpInsc, $cnpj, $funcionarioId);

        // R53: ambiente via config
        $tpAmb = (int) config('esocial.ambiente', 2);
        // R60: indRetif via parâmetro ou config
        $indRetif = $indRetif > 0 ? $indRetif : (int) config('esocial.ind_retif_default', 1);
        $verProc = (string) config('esocial.versao_proc', 'GENTE-v3');

        $cpfLimpo = preg_replace('/\D/', '', $func->PESSOA_CPF_NUMERO ?? '00000000000');
        $matricula = htmlspecialchars((string) ($func->FUNCIONARIO_MATRICULA ?? ''), ENT_XML1, 'UTF-8');
        $mtvLimpo = preg_replace('/\D/', '', $mtvDeslig);
        $mtvLimpo = str_pad(substr($mtvLimpo, 0, 2), 2, '0', STR_PAD_LEFT);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtDeslig/v02_01_00">
  <evtDeslig Id="{$idEvento}">
    <ideEvento>
      <indRetif>{$indRetif}</indRetif>
      <tpAmb>{$tpAmb}</tpAmb>
      <procEmi>1</procEmi>
      <verProc>{$verProc}</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>{$tpInsc}</tpInsc>
      <nrInsc>{$cnpj}</nrInsc>
    </ideEmpregador>
    <ideVinculo>
      <cpfTrab>{$cpfLimpo}</cpfTrab>
      <matricula>{$matricula}</matricula>
    </ideVinculo>
    <infoDeslig>
      <mtvDeslig>{$mtvLimpo}</mtvDeslig>
      <dtDeslig>{$dtDesligamento}</dtDeslig>
      <indPagtoAPI>N</indPagtoAPI>
    </infoDeslig>
  </evtDeslig>
</eSocial>
XML;

        return $xml;
    }
```

**Validação T5.5 (ambos os métodos):**

```powershell
php -l app/Services/EsocialXmlService.php
```

```powershell
# Confirmar que NÃO tem mais hardcodes de CNPJ
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "06205244000149"
```
Esperado: 0 ocorrências (todos via config).

```powershell
# Confirmar que NÃO tem mais tpAmb=1 hardcoded como literal
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "<tpAmb>1<"
```
Esperado: 0 ocorrências (todos via {$tpAmb} variável).

```powershell
# Confirmar que NÃO tem mais indRetif=1 hardcoded como literal
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "<indRetif>1<"
```
Esperado: 0 ocorrências (todos via {$indRetif} variável).

```powershell
# Confirmar que NÃO tem mais <perApur>$func->FUNCIONARIO_DATA_INICIO ou similar (R55)
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "perApur>\{\\\$func"
```
Esperado: 0 ocorrências.

```powershell
# Confirmar uso correto de config em todos os 4 métodos de geração XML
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "config\('esocial\.cnpj_empregador'\)|config\('esocial\.ambiente'\)" | Measure-Object | Select-Object Count
```
Esperado: 8+ (cnpj × 4 métodos + ambiente × 4 métodos).

**Commit:** `fix(Fase5-T5,R53-55-59-60): EsocialXmlService::gerarS2206/gerarS2299 usa config + remove perApur incorreto`

---

## T5.6 — Smoke test final + report (~15 min)

### Validação 1 — Sintaxe PHP de todos os arquivos modificados

```powershell
php -l app/Services/EsocialXmlService.php
php -l config/esocial.php
```
Esperado: `No syntax errors detected` em ambos.

### Validação 2 — Hardcodes erradicados

```powershell
# CNPJ hardcoded
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "06205244000149"
# Esperado: 0 ocorrências
```

```powershell
# tpAmb=1 literal em XML
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "<tpAmb>1<|<tpAmb>2<"
# Esperado: 0 ocorrências (sempre {$tpAmb})
```

```powershell
# indRetif=1 literal em XML
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "<indRetif>1<"
# Esperado: 0 ocorrências (sempre {$indRetif})
```

```powershell
# Dados pessoais hardcoded
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "<sexo>M<|<racaCor>1<|<estCiv>1<|<grauInstr>01<|<vrSalFx>1412\.00<|<cep>65000000<|Nao Informado"
# Esperado: 0 ocorrências
```

```powershell
# FOLHA_BRUTO removido (R52)
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "FOLHA_BRUTO"
# Esperado: 0 ocorrências
```

```powershell
# PESSOA_NASCIMENTO removido (bug bônus R52-B)
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "PESSOA_NASCIMENTO[^_]" -SimpleMatch
# Esperado: 0 ocorrências
```

### Validação 3 — Refatorações aplicadas

```powershell
# Uso de config('esocial.*')
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "config\('esocial\." | Measure-Object | Select-Object Count
# Esperado: 12+ ocorrências (3-4 per método × 4 métodos)
```

```powershell
# Helpers privados criados
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "function getEnderecoExpandido|function mapearDominio|function normalizarCompetenciaYm|function resolverSalarioFunc"
# Esperado: 4 ocorrências (uma definição cada)
```

```powershell
# Schema correto PESSOA_DATA_NASCIMENTO
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "PESSOA_DATA_NASCIMENTO"
# Esperado: 2+ ocorrências
```

```powershell
# Query S-1200 correta
Select-String -Path "app/Services/EsocialXmlService.php" -Pattern "DETALHE_FOLHA as df|DETALHE_FOLHA_PROVENTOS"
# Esperado: 2+ ocorrências
```

### Validação 4 — Git log das correções

```powershell
git log --oneline -n 8
```
Esperado: 5 commits novos:
- `feat(Fase5-T1): config/esocial.php com cnpj/ambiente/mapeamento centralizados`
- `fix(Fase5-T2,R52-B): EsocialXmlService::getFuncionarioDados usa schema real + helpers endereço/mapeamento`
- `fix(Fase5-T3,R52-53-55-59-60): EsocialXmlService::gerarS1200 corrige query DETALHE_FOLHA + config esocial`
- `fix(Fase5-T4,R54): EsocialXmlService::gerarS2200 lê dados reais de PESSOA + endereço expandido + salário via TABELA_SALARIAL`
- `fix(Fase5-T5,R53-55-59-60): EsocialXmlService::gerarS2206/gerarS2299 usa config + remove perApur incorreto`

### Validação 5 — Auditoria global eSocial (verificar que não sobrou nenhum hardcode)

```powershell
Get-ChildItem -Path "app", "config", "routes" -Recurse -Filter "*.php" | Select-String -Pattern "06205244000149"
# Esperado: 1 ocorrência (apenas em config/esocial.php como default da env var)
```

---

## REPORT TEMPLATE — preencha e devolva ao Ronaldo/Claude

```
═══════════════════════════════════════════════════════════════════
FASE 5 — REPORT EXECUÇÃO ANTYGRAVITY (data/hora: ____)
═══════════════════════════════════════════════════════════════════

CORREÇÕES (cole hash do commit):
[ ] T5.1 config/esocial.php estendido ........................ commit: ____
[ ] T5.2 getFuncionarioDados schema real + helpers ........... commit: ____
[ ] T5.3 gerarS1200 R52+R53+R55+R59+R60 ...................... commit: ____
[ ] T5.4 gerarS2200 R54+R53+R55+R59+R60 ...................... commit: ____
[ ] T5.5 gerarS2206/gerarS2299 R53+R55+R59+R60 ............... commit: ____

VALIDAÇÕES (cole saídas reais):

V1 php -l de todos os arquivos:
   ___ (esperado: 2× "No syntax errors detected")

V2 hardcodes erradicados:
   - 06205244000149 em EsocialXmlService.php: ___ (esperado 0)
   - <tpAmb>1< / <tpAmb>2<: ___ (esperado 0)
   - <indRetif>1<: ___ (esperado 0)
   - dados pessoais (sexo M/racaCor 1/etc): ___ (esperado 0)
   - FOLHA_BRUTO: ___ (esperado 0)
   - PESSOA_NASCIMENTO (sem _DATA): ___ (esperado 0)

V3 refatorações aplicadas:
   - config('esocial.*'): ___ ocorrências (esperado 12+)
   - 4 helpers privados (getEnderecoExpandido/mapearDominio/normalizarCompetenciaYm/resolverSalarioFunc): ___ (esperado 4)
   - PESSOA_DATA_NASCIMENTO: ___ ocorrências (esperado 2+)
   - DETALHE_FOLHA as df / DETALHE_FOLHA_PROVENTOS: ___ ocorrências (esperado 2+)

V4 git log -n 8:
   ___

V5 auditoria global CNPJ:
   ___ ocorrências (esperado 1, apenas em config/esocial.php)

BUGS ENCONTRADOS DURANTE EXECUÇÃO (não estavam na lista):
   ___

PROBLEMAS / DECISÕES TOMADAS QUE PRECISAM DE CONFIRMAÇÃO:
   ___

TEMPO TOTAL REAL: ___h ___min
═══════════════════════════════════════════════════════════════════
```

---

## CHECKLIST PÓS-FASE 5 (NÃO PARA ANTYGRAVITY)

Após Claude auditar e aprovar a Fase 5, atualizar o `.env` antes do go-live:

```env
ESOCIAL_CNPJ_EMPREGADOR=06205244000149
ESOCIAL_TIPO_INSCRICAO=1
ESOCIAL_AMBIENTE=2  # 2=homologação até confirmação com SEMFAZ; 1=produção real
ESOCIAL_VERSAO_PROC=GENTE-v3
ESOCIAL_IND_RETIF_DEFAULT=1
```

E confirmar que TABELA_GENERICA tem registros de mapeamento (sexo/raça/estado_civil/grau_instrução) coerentes com o config. Se os IDs em `config/esocial.php` (1,2,3...) não baterem com os IDs reais em TABELA_GENERICA da PMSL, ajustar o mapeamento antes do envio real.

---

## PRÓXIMA ETAPA APÓS APROVAÇÃO DA FASE 5

**Fase 4 — Remoção GRADUAL de rotas legadas** (sáb/dom).

Sequência cronológica para o deadline 12/05/2026:

```
[✅] Fase 1 — concluída + auditada (08/05 manhã)
[✅] Fase 2-A — concluída + auditada (08/05 tarde)
[✅] Fase 2-B — concluída + auditada (08/05 noite)
[ ] Fix GAP-MF-04 — em fila
[ ] Fase 3 — sex (~1h)
[ ] Fase 5 — sex/sáb (~1h30)
[ ] Fase 4 — sáb/dom (remoção rotas legadas, gradual)
[ ] Fase 6 — dom noite + seg madrugada (deploy PMSL)
[ ] PoC — seg 12/05 tarde
```

**FIM DO PROMPT FASE 5.**
