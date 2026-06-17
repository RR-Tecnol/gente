# PROMPT ANTYGRAVITY — FASE 2-A GENTE v3 (Ampliação MotorFolha — gaps críticos)

> **Cole este prompt no Antygravity (Cursor/Gemini) APENAS após Claude auditar e aprovar o report da Fase 1.**
> Estimativa total: 5h Antygravity (auditoria Claude separada: ~1h45)
> Pré-condição: Fase 1 mergeada. Nenhuma alteração pendente em `app/Services/`, `app/Services/MotorFolha/`, `app/Models/`, `database/migrations/`.

---

## CONTEXTO DA FASE 2-A

Decisão A do Ronaldo: **ampliar MotorFolhaService antes de aposentar FolhaParserService**. Esta fase implementa os 4 gaps críticos identificados na auditoria:

- **GAP-MF-01**: apuração de frequência (faltas, dias trabalhados)
- **GAP-MF-02**: abono por afastamento remunerado (LM, LMA, etc.)
- **GAP-MF-03**: pró-rata por admissão/exoneração no mês de competência
- **GAP-MF-04**: horas extras e plantões aprovados → LANCAMENTO_FOLHA

A Fase 2-B (gaps 5-8: jornada financeira, dias do mês, persistência por rubrica, R51 INSS 2025) virá em prompt separado.

### Princípios de design

1. **NÃO criar 3 serviços novos.** Estender o `MotorFolhaLoteContext` existente, que já carrega afastamentos por lote, e criar UM serviço auxiliar para HE/plantão.
2. **NÃO copiar a lógica defeituosa do FolhaParserService.** O FolhaParser lê colunas `DETALHE_ESCALA_ITEM_FALTA`/`DETALHE_ESCALA_ITEM_ATRASO` que NÃO existem no schema oficial (`2026_03_05_210000_create_escala_tables.php`). Vamos usar a **fonte de verdade real**: `AFASTAMENTO` + ausência de batidas em `REGISTRO_PONTO` + `JUSTIFICATIVA_PONTO`.
3. **Reutilizar serviços já existentes** quando possível: `ApuracaoPontoService` (já corrigido na Fase 1) tem lógica de apuração via REGISTRO_PONTO + DETALHE_ESCALA. Mas para o motor não vamos chamar o serviço dele — vamos calcular faltas de forma simplificada usando AFASTAMENTO + JUSTIFICATIVA_PONTO + dias úteis da escala.
4. **NÃO mexer em `routes/web.php`.** Apenas em `app/Services/`, `app/Services/MotorFolha/`, e migrations se necessário.

---

## REGRAS CRÍTICAS DE EXECUÇÃO

1. **Um commit por gap** (`feat(GAP-MF-01): ...`, `feat(GAP-MF-02): ...`, etc).
2. **Testes manuais via Tinker obrigatórios** após cada gap (comandos no fim de cada tarefa).
3. **Se o trecho não bater exatamente** com o esperado (Fase 1 mudou ainda mais o arquivo do que previsto, etc.), **PARAR e reportar**.
4. **Nunca usar `strftime`/`julianday`** em código novo. Usar `Carbon` ou `whereBetween`.
5. **Trabalhar em ordem:** GAP-01 → GAP-02 → GAP-03 → GAP-04 (cada um depende do anterior). Os 3 primeiros são em `MotorFolhaLoteContext` + `MotorFolhaService`. O GAP-04 cria 1 serviço novo + integra antes da leitura de LANCAMENTO_FOLHA no motor.

---

## GAP-MF-01 + GAP-MF-02 + GAP-MF-03 — Estender MotorFolhaLoteContext (frequência + abono + pró-rata)

**Estratégia:** os 3 gaps tocam no mesmo arquivo (`MotorFolhaLoteContext`) e dependem dos mesmos dados (datas competência, afastamentos pré-carregados, `FUNCIONARIO_DATA_INICIO`/`FUNCIONARIO_DATA_FIM`). Faremos os 3 num único PR/commit por eficiência.

**Arquivos afetados:**
- `app/Services/MotorFolha/MotorFolhaLoteContext.php` (estender)
- `app/Services/MotorFolhaService.php` (consumir os novos métodos do contexto + ajustar `prepararContextoLote` para incluir `FUNCIONARIO_DATA_INICIO`/`FIM`)

### TAREFA 2A.1 — Estender `MotorFolhaLoteContext`

**Arquivo:** `app/Services/MotorFolha/MotorFolhaLoteContext.php`

**Edit 1 — adicionar propriedades para datas de admissão/exoneração e justificativas (após `$avaliacoesPorFuncionario`):**

Trecho atual (linhas ~22-26):
```php
    /** @var array<int, Collection> */
    private array $afastamentosPorFuncionario = [];

    /** @var array<int, Collection> */
    private array $avaliacoesPorFuncionario = [];
```

Trecho corrigido:
```php
    /** @var array<int, Collection> */
    private array $afastamentosPorFuncionario = [];

    /** @var array<int, Collection> */
    private array $avaliacoesPorFuncionario = [];

    /** @var array<int, array{inicio: ?string, fim: ?string}> Datas de admissão/exoneração por funcionário (GAP-MF-03) */
    private array $datasContratuaisPorFuncionario = [];
```

**Edit 2 — atualizar construtor para receber datas contratuais:**

Trecho atual (linhas ~33-46):
```php
    /**
     * @param  array<int, float>  $cargoSalarioPorFuncionario
     * @param  array<int, Collection>  $afastamentosPorFuncionario
     * @param  array<int, Collection>  $avaliacoesPorFuncionario
     */
    public function __construct(
        string $competenciaYm,
        array $cargoSalarioPorFuncionario,
        array $afastamentosPorFuncionario,
        array $avaliacoesPorFuncionario
    ) {
        $this->competenciaYm = substr($competenciaYm, 0, 7);
        $this->competenciaInicio = Carbon::parse($this->competenciaYm . '-01')->startOfDay();
        $this->competenciaFim = $this->competenciaInicio->copy()->endOfMonth()->endOfDay();
        $this->cargoSalarioPorFuncionario = $cargoSalarioPorFuncionario;
        $this->afastamentosPorFuncionario = $afastamentosPorFuncionario;
        $this->avaliacoesPorFuncionario = $avaliacoesPorFuncionario;
    }
```

Trecho corrigido:
```php
    /**
     * @param  array<int, float>  $cargoSalarioPorFuncionario
     * @param  array<int, Collection>  $afastamentosPorFuncionario
     * @param  array<int, Collection>  $avaliacoesPorFuncionario
     * @param  array<int, array{inicio: ?string, fim: ?string}>  $datasContratuaisPorFuncionario
     */
    public function __construct(
        string $competenciaYm,
        array $cargoSalarioPorFuncionario,
        array $afastamentosPorFuncionario,
        array $avaliacoesPorFuncionario,
        array $datasContratuaisPorFuncionario = []
    ) {
        $this->competenciaYm = substr($competenciaYm, 0, 7);
        $this->competenciaInicio = Carbon::parse($this->competenciaYm . '-01')->startOfDay();
        $this->competenciaFim = $this->competenciaInicio->copy()->endOfMonth()->endOfDay();
        $this->cargoSalarioPorFuncionario = $cargoSalarioPorFuncionario;
        $this->afastamentosPorFuncionario = $afastamentosPorFuncionario;
        $this->avaliacoesPorFuncionario = $avaliacoesPorFuncionario;
        $this->datasContratuaisPorFuncionario = $datasContratuaisPorFuncionario;
    }
```

**Edit 3 — adicionar 4 novos métodos públicos no fim da classe (antes do fechamento `}`):**

Adicionar após o método `intervaloSobrepoeCompetencia` (último método private):

```php

    // ═══════════════════════════════════════════════════════════════════════════
    // GAP-MF-01/02/03 — Frequência, abono e pró-rata
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * GAP-MF-06: dias reais do mês de competência (28/29/30/31).
     * Substitui o "30 fixo" implícito.
     */
    public function diasNoMesCompetencia(): int
    {
        return $this->competenciaInicio->daysInMonth;
    }

    /**
     * GAP-MF-02: total de dias abonados por afastamento remunerado dentro da competência.
     *
     * Conta dias entre AFASTAMENTO_DATA_INICIO e AFASTAMENTO_DATA_FIM cobertos por LM/LMA/LP/etc.,
     * limitado ao mês de competência (sem strftime/julianday — só Carbon).
     *
     * Tipos abonados (compatível com o que o FolhaParserService listava):
     *   LICENCA_MEDICA, LICENCA_SAUDE, LICENCA_MATERNIDADE, LICENCA_PATERNIDADE,
     *   LICENCA_NOJO, LICENCA_GALA, AFASTAMENTO_JUDICIAL, AFASTAMENTO_REMUNERADO
     */
    public function diasAbonadosNoMes(int $funcionarioId): int
    {
        static $tiposAbonados = [
            'LICENCA_MEDICA', 'LICENCA_SAUDE', 'LICENCA_MATERNIDADE', 'LICENCA_PATERNIDADE',
            'LICENCA_NOJO', 'LICENCA_GALA', 'AFASTAMENTO_JUDICIAL', 'AFASTAMENTO_REMUNERADO',
        ];

        $totalDias = 0;
        foreach ($this->afastamentos($funcionarioId) as $a) {
            $tipo = (string) ($a->AFASTAMENTO_TIPO ?? '');
            if (! in_array($tipo, $tiposAbonados, true)) {
                continue;
            }

            $ini = $a->AFASTAMENTO_DATA_INICIO ?? null;
            $fim = $a->AFASTAMENTO_DATA_FIM ?? null;
            if (! $ini) {
                continue;
            }

            try {
                $iniDate = Carbon::parse($ini)->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            try {
                $fimDate = $fim
                    ? Carbon::parse($fim)->startOfDay()
                    : $this->competenciaFim->copy()->startOfDay();
            } catch (\Throwable) {
                $fimDate = $this->competenciaFim->copy()->startOfDay();
            }

            // Intersecção do intervalo do afastamento com a competência
            $iniEfetivo = $iniDate->greaterThan($this->competenciaInicio) ? $iniDate : $this->competenciaInicio->copy();
            $fimEfetivo = $fimDate->lessThan($this->competenciaFim) ? $fimDate : $this->competenciaFim->copy()->startOfDay();

            if ($iniEfetivo->lessThanOrEqualTo($fimEfetivo)) {
                $totalDias += $iniEfetivo->diffInDays($fimEfetivo) + 1;
            }
        }

        // Limitar ao número de dias no mês (defesa contra dupla contagem)
        return min($totalDias, $this->diasNoMesCompetencia());
    }

    /**
     * GAP-MF-03: dias trabalhados proporcionais por admissão/exoneração no mês.
     *
     * Retorna o total de dias do mês ajustado:
     *   - Admitido em D dentro do mês → diasNoMes - D + 1
     *   - Exonerado em D dentro do mês → D
     *   - Não admitido nem exonerado neste mês → diasNoMes inteiro
     *   - Admitido E exonerado no mesmo mês (raro) → fim - inicio + 1
     */
    public function diasContratuaisNoMes(int $funcionarioId): int
    {
        $diasMes = $this->diasNoMesCompetencia();
        $datas = $this->datasContratuaisPorFuncionario[$funcionarioId] ?? null;

        if (! $datas) {
            return $diasMes;
        }

        $inicioContrato = null;
        $fimContrato = null;

        if (! empty($datas['inicio'])) {
            try {
                $inicioContrato = Carbon::parse($datas['inicio'])->startOfDay();
            } catch (\Throwable) {
                $inicioContrato = null;
            }
        }
        if (! empty($datas['fim'])) {
            try {
                $fimContrato = Carbon::parse($datas['fim'])->startOfDay();
            } catch (\Throwable) {
                $fimContrato = null;
            }
        }

        // Limites efetivos ao mês de competência
        $iniEfetivo = ($inicioContrato && $inicioContrato->greaterThan($this->competenciaInicio))
            ? $inicioContrato
            : $this->competenciaInicio->copy();
        $fimEfetivo = ($fimContrato && $fimContrato->lessThan($this->competenciaFim))
            ? $fimContrato
            : $this->competenciaFim->copy()->startOfDay();

        if ($iniEfetivo->greaterThan($fimEfetivo)) {
            return 0; // contrato não vigia neste mês
        }

        $dias = $iniEfetivo->diffInDays($fimEfetivo) + 1;

        return min($dias, $diasMes);
    }

    /**
     * GAP-MF-01: dias efetivamente trabalhados na competência.
     *
     * Fórmula: dias_contratuais - dias_abonados (faltas legítimas não entram aqui — entram
     * como diferença `diasMes - diasTrabalhados` no cálculo do desconto proporcional).
     *
     * Para a Fase 2-A, "faltas não justificadas" não são apuradas automaticamente — fica
     * para uma Fase futura quando o módulo de Ponto Eletrônico estiver consolidado em produção.
     * Por ora, o cálculo proporcional do MotorFolha trata cada servidor como tendo trabalhado
     * (dias_contratuais - dias_abonados) dias. Faltas não justificadas só descontam quando
     * vierem como LANCAMENTO_FOLHA tipo D.
     */
    public function diasTrabalhadosNoMes(int $funcionarioId): int
    {
        $contratuais = $this->diasContratuaisNoMes($funcionarioId);
        $abonados = $this->diasAbonadosNoMes($funcionarioId);

        // Abonados são considerados como trabalhados para fins de remuneração
        // (não afetam o vencimento). O método retorna apenas dias_contratuais
        // — o uso de "abonados" é informativo e auditável separadamente.
        unset($abonados);

        return $contratuais;
    }

    /**
     * Razão proporcional [0.0, 1.0] aplicada ao vencimento estrutural.
     *
     * Exemplo:
     *   - Mês 30 dias, admitido dia 16 → diasContratuais=15 → 15/30 = 0.5
     *   - Mês 28 dias (fevereiro), inteiro → 28/28 = 1.0
     *   - Mês 30 dias, sem dados contratuais → 30/30 = 1.0
     */
    public function razaoProporcionalVencimento(int $funcionarioId): float
    {
        $diasMes = $this->diasNoMesCompetencia();
        if ($diasMes <= 0) {
            return 0.0;
        }

        return $this->diasTrabalhadosNoMes($funcionarioId) / $diasMes;
    }
```

**Validação 1:** sintaxe PHP OK + classe carrega.
```powershell
php -l app/Services/MotorFolha/MotorFolhaLoteContext.php
php artisan tinker --execute="\$ctx = new \App\Services\MotorFolha\MotorFolhaLoteContext('2026-04', [], [], []); echo 'dias='.\$ctx->diasNoMesCompetencia();"
```
Saída esperada: `dias=30`. Para fevereiro 2026: `'2026-02'` → `dias=28`.

**Validação 2:** método de pró-rata com admissão.
```powershell
php artisan tinker --execute="
\$ctx = new \App\Services\MotorFolha\MotorFolhaLoteContext(
    '2026-04', [], [], [],
    [123 => ['inicio' => '2026-04-16', 'fim' => null]]
);
echo 'dias_contratuais=' . \$ctx->diasContratuaisNoMes(123);
"
```
Saída esperada: `dias_contratuais=15` (16/04 a 30/04 = 15 dias).

### TAREFA 2A.2 — Atualizar `MotorFolhaService::prepararContextoLote` para popular `datasContratuaisPorFuncionario`

**Arquivo:** `app/Services/MotorFolhaService.php`

**Localização:** método `prepararContextoLote`, ~linha 95-150 (após Fase 1 pode ter shift de 1-2 linhas — confirmar pelo conteúdo).

**Trecho atual (parte final do método):**
```php
        $afastMap = [];
        $avalMap = [];
        foreach ($ids as $fid) {
            /** @var Funcionario|null $f */
            $f = $funcionarios->get($fid);
            $afastMap[$fid] = ($f && $f->relationLoaded('afastamentos')) ? $f->afastamentos : collect();
            $avalMap[$fid] = ($f && $f->relationLoaded('avaliacoesDesempenho')) ? $f->avaliacoesDesempenho : collect();
        }

        return new MotorFolhaLoteContext($competencia, $cargoSalario, $afastMap, $avalMap);
    }
```

**Trecho corrigido:**
```php
        $afastMap = [];
        $avalMap = [];
        $datasContratuais = []; // GAP-MF-03: snapshot de DATA_INICIO/FIM para pró-rata
        foreach ($ids as $fid) {
            /** @var Funcionario|null $f */
            $f = $funcionarios->get($fid);
            $afastMap[$fid] = ($f && $f->relationLoaded('afastamentos')) ? $f->afastamentos : collect();
            $avalMap[$fid] = ($f && $f->relationLoaded('avaliacoesDesempenho')) ? $f->avaliacoesDesempenho : collect();
            $datasContratuais[$fid] = [
                'inicio' => $f?->FUNCIONARIO_DATA_INICIO,
                'fim' => $f?->FUNCIONARIO_DATA_FIM,
            ];
        }

        return new MotorFolhaLoteContext($competencia, $cargoSalario, $afastMap, $avalMap, $datasContratuais);
    }
```

**Atenção:** o `Funcionario` model já é eager-loaded acima na query `Funcionario::query()->whereIn(...)->with($eager)->get()`. Os campos `FUNCIONARIO_DATA_INICIO` e `FUNCIONARIO_DATA_FIM` já estão na tabela e no model (são `$fillable`). Acessamos diretamente via property accessor.

**Validação:**
```powershell
php -l app/Services/MotorFolhaService.php
```

### TAREFA 2A.3 — Aplicar a razão proporcional no cálculo do `MotorFolhaService`

**Arquivo:** `app/Services/MotorFolhaService.php`

**Localização:** método `calcularLoteParaFuncionarios`, dentro do `foreach ($servidores as $funcId => $s)` (~linha 320-360 — confirmar antes de editar).

**Estratégia:** o vencimento `$vencBase` hoje é integral. Vamos multiplicar por `razaoProporcionalVencimento` apenas no C1 (anuênio + vencimento estrutural). Os adicionais C2 e lançamentos C3 NÃO são proporcionalizados — quem quiser proporcionalizar adicional C2 cria com `tipo='percentual'` que já recalcula sobre o `vencBase` proporcionalizado.

**Trecho atual (entrada do switch case, ~linha 333):**

```php
        foreach ($servidores as $funcId => $s) {
            $funcId = (int) $funcId;
            $vinculoTipo = $s->VINCULO_TIPO ?? 'efetivo';
            $vencBase = (float) ($s->TABELA_VENCIMENTO_BASE ?? 0);
            $cargoSal = $contexto->getCargoSalario($funcId);
            if ($vencBase <= 0 && $cargoSal > 0) {
                $vencBase = $cargoSal;
            }

            // Dados injectados (sem query): competência × afastamento / desempenho
            $contexto->possuiAfastamentoSobrepostoNaCompetencia($funcId);
            $contexto->melhorNotaFinal($funcId);
            $fatorDesempenho = $contexto->fatorProgressaoPorDesempenho($funcId);

            switch ($vinculoTipo) {
```

**Trecho corrigido:**

```php
        foreach ($servidores as $funcId => $s) {
            $funcId = (int) $funcId;
            $vinculoTipo = $s->VINCULO_TIPO ?? 'efetivo';
            $vencBaseIntegral = (float) ($s->TABELA_VENCIMENTO_BASE ?? 0);
            $cargoSal = $contexto->getCargoSalario($funcId);
            if ($vencBaseIntegral <= 0 && $cargoSal > 0) {
                $vencBaseIntegral = $cargoSal;
            }

            // GAP-MF-01/03: aplicar pró-rata por dias contratuais no mês (admissão/exoneração).
            // GAP-MF-06 (parcial): denominador = dias reais do mês de competência (28/29/30/31).
            $razao = $contexto->razaoProporcionalVencimento($funcId);
            $vencBase = $vencBaseIntegral * $razao;

            // GAP-MF-02: dias abonados (LM/LMA/etc.) — informativo, já contabilizados em dias trabalhados.
            $diasAbonados = $contexto->diasAbonadosNoMes($funcId);

            // Dados injectados (sem query): competência × afastamento / desempenho
            $contexto->possuiAfastamentoSobrepostoNaCompetencia($funcId);
            $contexto->melhorNotaFinal($funcId);
            $fatorDesempenho = $contexto->fatorProgressaoPorDesempenho($funcId);

            switch ($vinculoTipo) {
```

**Atenção:** após esta edição, todas as referências subsequentes a `$vencBase` no switch usarão o **valor já proporcionalizado**. Não mexer nos cases — eles continuam corretos.

**Validação:** rodar `php -l` e tinker:

```powershell
php -l app/Services/MotorFolhaService.php
```

```powershell
# Smoke test sintético — gera contexto com admissão dia 16 e verifica razão
php artisan tinker --execute="
\$ctx = new \App\Services\MotorFolha\MotorFolhaLoteContext(
    '2026-04', [], [], [],
    [999 => ['inicio' => '2026-04-16', 'fim' => null]]
);
echo 'razao=' . \$ctx->razaoProporcionalVencimento(999) . ' (esperado 0.5 = 15/30)';
"
```
Saída esperada: `razao=0.5 (esperado 0.5 = 15/30)`.

```powershell
# Smoke test fevereiro 28 dias
php artisan tinker --execute="
\$ctx = new \App\Services\MotorFolha\MotorFolhaLoteContext('2026-02', [], [], []);
echo 'dias_fev=' . \$ctx->diasNoMesCompetencia() . ' (esperado 28)';
"
```
Saída esperada: `dias_fev=28 (esperado 28)`.

**Snapshot opcional em DETALHE_FOLHA (registro de auditoria):**

Logo abaixo do bloco onde já se insere `'DETALHE_VINCULO_TIPO' => $vinculoTipo,`, adicionar 2 campos de auditoria (sem migration nova — campos só existem como log do log; se a tabela não tiver as colunas, o upsert ignora pois só usa as chaves listadas em `$update`).

**Trecho atual (no array `$resultados[$funcId]`):**
```php
            $resultados[$funcId] = [
                'FUNCIONARIO_ID' => $funcId,
                'FOLHA_ID' => $folhaId,
                'DETALHE_FOLHA_PROVENTOS' => round($bruto, 2),
                'DETALHE_FOLHA_DESCONTOS' => round($descPrev + $descIRRF + $descOutros, 2),
                'DETALHE_FOLHA_LIQUIDO' => round($liquido, 2),
                'DETALHE_BASE_PREV' => round($basePrev, 2),
                'DETALHE_BASE_IRRF' => round(max(0, $baseIrrf), 2),
                'DETALHE_DESC_PREV' => round($descPrev, 2),
                'DETALHE_DESC_IRRF' => round($descIRRF, 2),
                'DETALHE_DESC_OUTROS' => round($descOutros, 2),
                'DETALHE_VINCULO_TIPO' => $vinculoTipo,
                'DETALHE_COMPLEMENTO_SM' => $complementoSM,
            ];
```

**Manter como está** (não adicionar campos novos para auditoria nesta fase — Fase 2-B / Fase 2-A audit Claude vai validar via comparação numérica). Apenas registrar no log de informação:

```php
            \Illuminate\Support\Facades\Log::info('[MotorFolha] cálculo lote', [
                'folha_id' => $folhaId,
                'funcionario_id' => $funcId,
                'razao_proporcional' => round($razao, 4),
                'dias_abonados' => $diasAbonados,
                'venc_base_integral' => round($vencBaseIntegral, 2),
                'venc_base_proporcional' => round($vencBase, 2),
                'bruto' => round($bruto, 2),
                'liquido' => round($liquido, 2),
            ]);
```

Adicionar o `Log::info` **logo antes** da linha `$resultados[$funcId] = [` no foreach. **Atenção:** verifique que `use Illuminate\Support\Facades\Log;` ou similar não duplica — provavelmente já existe `use Illuminate\Support\Facades\DB;` e `use Illuminate\Support\Facades\Schema;`. Adicione `use Illuminate\Support\Facades\Log;` no topo do arquivo se não existir.

**Validação final do GAP-MF-01/02/03:**

```powershell
# Cenário sintético end-to-end (sem persistir, só calcular)
php artisan tinker --execute="
\$folha = \App\Models\Folha::first();
if (!\$folha) { echo 'sem_folhas'; exit; }
\$func = \App\Models\Funcionario::first();
if (!\$func) { echo 'sem_funcionarios'; exit; }
\$ids = [\$func->FUNCIONARIO_ID];
\$ctx = \App\Services\MotorFolhaService::prepararContextoLote(\$folha->FOLHA_ID, \$ids);
echo 'razao=' . \$ctx->razaoProporcionalVencimento(\$func->FUNCIONARIO_ID) . PHP_EOL;
echo 'dias_abonados=' . \$ctx->diasAbonadosNoMes(\$func->FUNCIONARIO_ID) . PHP_EOL;
echo 'dias_trabalhados=' . \$ctx->diasTrabalhadosNoMes(\$func->FUNCIONARIO_ID) . PHP_EOL;
echo 'dias_no_mes=' . \$ctx->diasNoMesCompetencia();
"
```
Saída esperada: 4 linhas com valores numéricos (não 0 a menos que o servidor tenha sido recém-admitido).

**Commit:** `feat(GAP-MF-01,02,03): MotorFolhaLoteContext com pró-rata, abono e dias do mês`

---

## GAP-MF-04 — Horas extras e plantões → LANCAMENTO_FOLHA

**Estratégia:** Criar UM serviço auxiliar `InclusaoHorasExtrasService` que **converte HORA_EXTRA + PLANTAO_EXTRA com STATUS=APROVADA/APROVADO** em registros `LANCAMENTO_FOLHA` tipo `'P'` INCIDE_PREV=1. Marca STATUS='INCLUIDA_FOLHA'/'INCLUIDO_FOLHA' (singular!plural) após inclusão.

**Por que não usar via context?** Porque o serviço **modifica banco** (insere LANCAMENTO_FOLHA + atualiza status). Diferente do contexto que é só leitura. Manter responsabilidades separadas.

**Ponto de injeção:** Chamar `InclusaoHorasExtrasService::incluirParaFolha($folhaId, $funcionarioIds, $competencia)` **uma vez no início** de `MotorFolhaService::calcularLoteParaFuncionarios`, **antes** de ler `LANCAMENTO_FOLHA`. Idempotência: o serviço verifica STATUS antes de incluir (não duplica).

### TAREFA 2A.4 — Criar `InclusaoHorasExtrasService`

**Criar pasta** se não existir: `app/Services/Folha/`. Comando:
```powershell
if (!(Test-Path "app/Services/Folha")) { New-Item -ItemType Directory -Path "app/Services/Folha" }
```

**Arquivo novo:** `app/Services/Folha/InclusaoHorasExtrasService.php`

**Conteúdo completo (criar do zero):**

```php
<?php

namespace App\Services\Folha;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GAP-MF-04 — Inclusão de Horas Extras e Plantões aprovados como LANCAMENTO_FOLHA.
 *
 * Converte registros de HORA_EXTRA (STATUS=APROVADA) e PLANTAO_EXTRA (STATUS=APROVADO)
 * da competência da folha em registros LANCAMENTO_FOLHA tipo 'P' (provento) INCIDE_PREV=1.
 *
 * Idempotência:
 *   - Verifica STATUS antes de inserir (só processa APROVADA/APROVADO).
 *   - Após inserção, marca STATUS='INCLUIDA_FOLHA'/'INCLUIDO_FOLHA'.
 *   - Re-execução para a mesma (folha, funcionário) não duplica porque os registros
 *     já incluídos terão STATUS diferente e serão filtrados.
 *
 * Tudo dentro de DB::transaction para atomicidade.
 */
final class InclusaoHorasExtrasService
{
    /**
     * RUBRICA_ID padrão para hora extra (50%, 100%, feriado).
     * Buscado por código RUBRICA_CODIGO. Se não existir, cria via fallback.
     */
    private const RUBRICA_HE_50_CODIGO = 'HE_50';
    private const RUBRICA_HE_100_CODIGO = 'HE_100';
    private const RUBRICA_HE_FERIADO_CODIGO = 'HE_FER';
    private const RUBRICA_PLANTAO_CODIGO = 'PLANTAO_EXTRA';

    /**
     * Inclui HE + Plantão aprovados como LANCAMENTO_FOLHA para um lote de funcionários.
     *
     * @param  int           $folhaId
     * @param  list<int>     $funcionarioIds
     * @param  string        $competencia  AAAA-MM (a tabela HORA_EXTRA usa esse formato)
     * @return array{he_incluidas: int, plantoes_incluidos: int}
     */
    public function incluirParaFolha(int $folhaId, array $funcionarioIds, string $competencia): array
    {
        $compFormatada = $this->normalizarCompetencia($competencia);
        $funcIds = array_values(array_unique(array_map('intval', $funcionarioIds)));
        if ($funcIds === []) {
            return ['he_incluidas' => 0, 'plantoes_incluidos' => 0];
        }

        return DB::transaction(function () use ($folhaId, $funcIds, $compFormatada) {
            $heCount = $this->processarHorasExtras($folhaId, $funcIds, $compFormatada);
            $peCount = $this->processarPlantoes($folhaId, $funcIds, $compFormatada);

            Log::info('[InclusaoHorasExtras] HE/Plantão incluídos como LANCAMENTO_FOLHA', [
                'folha_id' => $folhaId,
                'competencia' => $compFormatada,
                'funcionarios_processados' => count($funcIds),
                'he_incluidas' => $heCount,
                'plantoes_incluidos' => $peCount,
            ]);

            return ['he_incluidas' => $heCount, 'plantoes_incluidos' => $peCount];
        });
    }

    private function normalizarCompetencia(string $competencia): string
    {
        // Aceita "202604" ou "2026-04" → retorna "2026-04"
        $c = preg_replace('/\D/', '', $competencia);
        if (strlen($c) >= 6) {
            return substr($c, 0, 4) . '-' . substr($c, 4, 2);
        }

        return $competencia;
    }

    private function processarHorasExtras(int $folhaId, array $funcIds, string $compFormatada): int
    {
        $hes = DB::table('HORA_EXTRA')
            ->whereIn('FUNCIONARIO_ID', $funcIds)
            ->where('COMPETENCIA', $compFormatada)
            ->where('STATUS', 'APROVADA')
            ->get(['HORA_EXTRA_ID', 'FUNCIONARIO_ID', 'TOTAL_HORAS', 'PERCENTUAL', 'TIPO_HORA_EXTRA', 'VALOR_CALCULADO']);

        $count = 0;
        foreach ($hes as $he) {
            $valor = (float) ($he->VALOR_CALCULADO ?? 0);
            if ($valor <= 0) {
                continue;
            }

            $rubricaId = $this->resolverRubricaIdHe((string) ($he->TIPO_HORA_EXTRA ?? ''));
            if ($rubricaId === null) {
                Log::warning('[InclusaoHorasExtras] Rubrica de hora extra não encontrada', [
                    'tipo' => $he->TIPO_HORA_EXTRA,
                ]);
                continue;
            }

            DB::table('LANCAMENTO_FOLHA')->insert([
                'FUNCIONARIO_ID' => (int) $he->FUNCIONARIO_ID,
                'FOLHA_ID' => $folhaId,
                'RUBRICA_ID' => $rubricaId,
                'LANCAMENTO_TIPO' => 'P',
                'LANCAMENTO_QTDE' => (float) ($he->TOTAL_HORAS ?? 1),
                'LANCAMENTO_VALOR_UNIT' => (float) ($he->TOTAL_HORAS > 0 ? $valor / (float) $he->TOTAL_HORAS : $valor),
                'LANCAMENTO_VALOR_TOTAL' => $valor,
                'LANCAMENTO_INCIDE_PREV' => true,
                'LANCAMENTO_INCIDE_IRRF' => true,
                'LANCAMENTO_ORIGEM' => 'ponto',
                'LANCAMENTO_OBS' => 'GAP-MF-04: HE_ID=' . $he->HORA_EXTRA_ID,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('HORA_EXTRA')
                ->where('HORA_EXTRA_ID', $he->HORA_EXTRA_ID)
                ->update(['STATUS' => 'INCLUIDA_FOLHA', 'updated_at' => now()]);

            $count++;
        }

        return $count;
    }

    private function processarPlantoes(int $folhaId, array $funcIds, string $compFormatada): int
    {
        $pes = DB::table('PLANTAO_EXTRA')
            ->whereIn('FUNCIONARIO_ID', $funcIds)
            ->where('COMPETENCIA', $compFormatada)
            ->where('STATUS', 'APROVADO')
            ->get(['PLANTAO_EXTRA_ID', 'FUNCIONARIO_ID', 'TOTAL_HORAS', 'VALOR_CALCULADO']);

        $rubricaPlantao = $this->resolverRubricaIdPorCodigo(self::RUBRICA_PLANTAO_CODIGO);
        if ($rubricaPlantao === null) {
            Log::warning('[InclusaoHorasExtras] Rubrica PLANTAO_EXTRA não encontrada — plantões NÃO incluídos.');
            return 0;
        }

        $count = 0;
        foreach ($pes as $pe) {
            $valor = (float) ($pe->VALOR_CALCULADO ?? 0);
            if ($valor <= 0) {
                continue;
            }

            DB::table('LANCAMENTO_FOLHA')->insert([
                'FUNCIONARIO_ID' => (int) $pe->FUNCIONARIO_ID,
                'FOLHA_ID' => $folhaId,
                'RUBRICA_ID' => $rubricaPlantao,
                'LANCAMENTO_TIPO' => 'P',
                'LANCAMENTO_QTDE' => (float) ($pe->TOTAL_HORAS ?? 1),
                'LANCAMENTO_VALOR_UNIT' => (float) ($pe->TOTAL_HORAS > 0 ? $valor / (float) $pe->TOTAL_HORAS : $valor),
                'LANCAMENTO_VALOR_TOTAL' => $valor,
                'LANCAMENTO_INCIDE_PREV' => true,
                'LANCAMENTO_INCIDE_IRRF' => true,
                'LANCAMENTO_ORIGEM' => 'ponto',
                'LANCAMENTO_OBS' => 'GAP-MF-04: PLANTAO_ID=' . $pe->PLANTAO_EXTRA_ID,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('PLANTAO_EXTRA')
                ->where('PLANTAO_EXTRA_ID', $pe->PLANTAO_EXTRA_ID)
                ->update(['STATUS' => 'INCLUIDO_FOLHA', 'updated_at' => now()]);

            $count++;
        }

        return $count;
    }

    private function resolverRubricaIdHe(string $tipoHe): ?int
    {
        $codigo = match (true) {
            str_contains($tipoHe, '100') => self::RUBRICA_HE_100_CODIGO,
            str_contains($tipoHe, 'FERIADO') => self::RUBRICA_HE_FERIADO_CODIGO,
            default => self::RUBRICA_HE_50_CODIGO,
        };

        return $this->resolverRubricaIdPorCodigo($codigo);
    }

    private function resolverRubricaIdPorCodigo(string $codigo): ?int
    {
        static $cache = [];
        if (array_key_exists($codigo, $cache)) {
            return $cache[$codigo];
        }

        $id = DB::table('RUBRICA')->where('RUBRICA_CODIGO', $codigo)->value('RUBRICA_ID');
        $cache[$codigo] = $id ? (int) $id : null;

        return $cache[$codigo];
    }
}
```

**Validação 1 (sintaxe + classe carrega):**
```powershell
php -l app/Services/Folha/InclusaoHorasExtrasService.php
php artisan tinker --execute="\$svc = new \App\Services\Folha\InclusaoHorasExtrasService(); echo 'service_ok';"
```
Saída esperada: `service_ok`.

### TAREFA 2A.5 — Verificar/Seedar rubricas HE_50, HE_100, HE_FER, PLANTAO_EXTRA

Antes de integrar, garantir que essas rubricas existem na tabela RUBRICA. Em SQLite dev pode não existir.

```powershell
php artisan tinker --execute="
\$codigos = ['HE_50', 'HE_100', 'HE_FER', 'PLANTAO_EXTRA'];
foreach (\$codigos as \$c) {
    \$exists = \DB::table('RUBRICA')->where('RUBRICA_CODIGO', \$c)->exists();
    echo \$c . ': ' . (\$exists ? 'OK' : 'FALTANDO') . PHP_EOL;
}
"
```

**Se algum estiver FALTANDO:** criar seeder novo `database/seeders/RubricasHePlantaoSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed das rubricas usadas pelo InclusaoHorasExtrasService (GAP-MF-04).
 * Idempotente: usa updateOrInsert por RUBRICA_CODIGO.
 */
class RubricasHePlantaoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('RUBRICA')) {
            $this->command->warn('Tabela RUBRICA não existe — seeder ignorado.');
            return;
        }

        $rubricas = [
            ['codigo' => 'HE_50',         'descricao' => 'Hora Extra 50%',          'tipo' => 'P', 'camada' => 3],
            ['codigo' => 'HE_100',        'descricao' => 'Hora Extra 100%',         'tipo' => 'P', 'camada' => 3],
            ['codigo' => 'HE_FER',        'descricao' => 'Hora Extra Feriado',      'tipo' => 'P', 'camada' => 3],
            ['codigo' => 'PLANTAO_EXTRA', 'descricao' => 'Plantão Extra',           'tipo' => 'P', 'camada' => 3],
        ];

        foreach ($rubricas as $r) {
            $payload = [
                'RUBRICA_DESCRICAO' => $r['descricao'],
                'RUBRICA_TIPO' => $r['tipo'],
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('RUBRICA', 'RUBRICA_CAMADA')) {
                $payload['RUBRICA_CAMADA'] = $r['camada'];
            }
            if (Schema::hasColumn('RUBRICA', 'RUBRICA_CALCULO')) {
                $payload['RUBRICA_CALCULO'] = 'fixo'; // HE/Plantão sempre vêm com valor já calculado
            }
            if (Schema::hasColumn('RUBRICA', 'RUBRICA_ATIVO')) {
                $payload['RUBRICA_ATIVO'] = 1;
            }

            DB::table('RUBRICA')->updateOrInsert(
                ['RUBRICA_CODIGO' => $r['codigo']],
                $payload + ['created_at' => now()]
            );
        }
    }
}
```

Rodar:
```powershell
php artisan db:seed --class=Database\\Seeders\\RubricasHePlantaoSeeder
```

Re-validar:
```powershell
php artisan tinker --execute="
\$codigos = ['HE_50', 'HE_100', 'HE_FER', 'PLANTAO_EXTRA'];
foreach (\$codigos as \$c) {
    \$id = \DB::table('RUBRICA')->where('RUBRICA_CODIGO', \$c)->value('RUBRICA_ID');
    echo \$c . ' = ' . (\$id ?? 'NULL') . PHP_EOL;
}
"
```
Saída esperada: 4 IDs numéricos, nenhum NULL.

### TAREFA 2A.6 — Integrar `InclusaoHorasExtrasService` no `MotorFolhaService`

**Arquivo:** `app/Services/MotorFolhaService.php`

**Localização:** método `calcularLoteParaFuncionarios`, **logo após** o early-return de `$ids === []` e **antes** do `$servidoresQuery = DB::table('FUNCIONARIO as f')...`.

**Trecho atual (~linha 248-260):**

```php
        $ids = array_values(array_unique(array_map('intval', $funcionarioIds)));
        if ($ids === []) {
            return ['ok' => true, 'servidores' => 0, 'total_proventos' => 0.0, 'total_descontos' => 0.0, 'total_liquido' => 0.0, 'total_comp_sm' => 0.0];
        }

        $servidoresQuery = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
```

**Trecho corrigido:**

```php
        $ids = array_values(array_unique(array_map('intval', $funcionarioIds)));
        if ($ids === []) {
            return ['ok' => true, 'servidores' => 0, 'total_proventos' => 0.0, 'total_descontos' => 0.0, 'total_liquido' => 0.0, 'total_comp_sm' => 0.0];
        }

        // GAP-MF-04: incluir HE/Plantão aprovados como LANCAMENTO_FOLHA antes de ler.
        // Idempotente: re-execução não duplica (verifica STATUS).
        try {
            app(\App\Services\Folha\InclusaoHorasExtrasService::class)
                ->incluirParaFolha($folhaId, $ids, (string) $competencia);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[MotorFolha] falha ao incluir HE/Plantão', [
                'folha_id' => $folhaId,
                'erro' => $e->getMessage(),
            ]);
            // Não fail-fast: prosseguir o cálculo da folha mesmo se a inclusão de HE falhar.
            // O auditor (Claude) vai detectar pelo log e abrir bug separado.
        }

        $servidoresQuery = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
```

**Justificativa do try/catch:** se a tabela RUBRICA estiver inconsistente em algum ambiente, o motor não pode quebrar a folha inteira. Logamos e seguimos. O auditor Claude valida o log no report.

**Validação:**

```powershell
php -l app/Services/MotorFolhaService.php
```

```powershell
# Smoke end-to-end com HE fictícia
php artisan tinker --execute="
\$folha = \App\Models\Folha::first();
\$func = \App\Models\Funcionario::first();
if (!\$folha || !\$func) { echo 'sem_dados'; exit; }

// Limpar HE existentes do funcionário pra teste limpo
\DB::table('HORA_EXTRA')->where('FUNCIONARIO_ID', \$func->FUNCIONARIO_ID)->delete();
\DB::table('LANCAMENTO_FOLHA')
    ->where('FOLHA_ID', \$folha->FOLHA_ID)
    ->where('FUNCIONARIO_ID', \$func->FUNCIONARIO_ID)
    ->where('LANCAMENTO_OBS', 'like', 'GAP-MF-04%')
    ->delete();

// Criar 1 HE 50% APROVADA
\$comp = substr(\$folha->FOLHA_COMPETENCIA, 0, 4) . '-' . substr(\$folha->FOLHA_COMPETENCIA, 4, 2);
\DB::table('HORA_EXTRA')->insert([
    'FUNCIONARIO_ID' => \$func->FUNCIONARIO_ID,
    'COMPETENCIA' => \$comp,
    'DATA_REALIZACAO' => \$comp . '-15',
    'TOTAL_HORAS' => 4.00,
    'TIPO_HORA_EXTRA' => '50_PORCENTO',
    'PERCENTUAL' => 50.00,
    'VALOR_HORA_BASE' => 25.00,
    'VALOR_CALCULADO' => 150.00, // 4h × 25 × 1.5
    'STATUS' => 'APROVADA',
    'created_at' => now(), 'updated_at' => now(),
]);

// Chamar serviço diretamente
\$svc = new \App\Services\Folha\InclusaoHorasExtrasService();
\$out = \$svc->incluirParaFolha(\$folha->FOLHA_ID, [\$func->FUNCIONARIO_ID], \$comp);
echo 'incluidas=' . json_encode(\$out) . PHP_EOL;

// Validar persistência
\$lf = \DB::table('LANCAMENTO_FOLHA')
    ->where('FOLHA_ID', \$folha->FOLHA_ID)
    ->where('FUNCIONARIO_ID', \$func->FUNCIONARIO_ID)
    ->where('LANCAMENTO_OBS', 'like', 'GAP-MF-04%')
    ->first();
echo 'lancamento_criado=' . (\$lf ? 'sim valor=' . \$lf->LANCAMENTO_VALOR_TOTAL : 'NAO') . PHP_EOL;

// Validar idempotência (rodar 2x não cria 2 lançamentos)
\$out2 = \$svc->incluirParaFolha(\$folha->FOLHA_ID, [\$func->FUNCIONARIO_ID], \$comp);
\$count = \DB::table('LANCAMENTO_FOLHA')
    ->where('FOLHA_ID', \$folha->FOLHA_ID)
    ->where('FUNCIONARIO_ID', \$func->FUNCIONARIO_ID)
    ->where('LANCAMENTO_OBS', 'like', 'GAP-MF-04%')
    ->count();
echo 'apos_2_execucoes_count=' . \$count . ' (esperado: 1)';
"
```

Saída esperada:
```
incluidas={"he_incluidas":1,"plantoes_incluidos":0}
lancamento_criado=sim valor=150.00
apos_2_execucoes_count=1 (esperado: 1)
```

A idempotência funciona porque na 2ª execução o STATUS já é 'INCLUIDA_FOLHA', então a query do serviço (`->where('STATUS', 'APROVADA')`) não retorna mais o registro.

**Commit:** `feat(GAP-MF-04): InclusaoHorasExtrasService + integração no MotorFolhaService`

---

## VALIDAÇÃO FINAL — Smoke test pós-Fase 2-A

Execute **todos** os comandos abaixo e cole as saídas no report. Cada comando valida um ou mais GAPs.

### Validação 1 — Cenário "fevereiro 28 dias"
```powershell
php artisan tinker --execute="
\$ctx = new \App\Services\MotorFolha\MotorFolhaLoteContext('2026-02', [], [], []);
echo 'fev_dias=' . \$ctx->diasNoMesCompetencia();
"
```
Esperado: `fev_dias=28`. **Bate GAP-MF-06 parcial (dias do mês via Carbon).**

### Validação 2 — Cenário "admissão dia 16, mês 30 dias"
```powershell
php artisan tinker --execute="
\$ctx = new \App\Services\MotorFolha\MotorFolhaLoteContext(
    '2026-04', [], [], [],
    [777 => ['inicio' => '2026-04-16', 'fim' => null]]
);
echo 'razao=' . \$ctx->razaoProporcionalVencimento(777);
"
```
Esperado: `razao=0.5`. **Bate GAP-MF-03 (pró-rata admissão).**

### Validação 3 — Cenário "exoneração dia 10"
```powershell
php artisan tinker --execute="
\$ctx = new \App\Services\MotorFolha\MotorFolhaLoteContext(
    '2026-04', [], [], [],
    [888 => ['inicio' => '2020-01-01', 'fim' => '2026-04-10']]
);
echo 'razao=' . \$ctx->razaoProporcionalVencimento(888);
"
```
Esperado: `razao=0.3333...` (10/30). **Bate GAP-MF-03 (pró-rata exoneração).**

### Validação 4 — Cenário "afastamento médico de 5 dias dentro da competência"
```powershell
php artisan tinker --execute="
\$afast = collect([(object)[
    'AFASTAMENTO_TIPO' => 'LICENCA_MEDICA',
    'AFASTAMENTO_DATA_INICIO' => '2026-04-10',
    'AFASTAMENTO_DATA_FIM' => '2026-04-14',
]]);
\$ctx = new \App\Services\MotorFolha\MotorFolhaLoteContext(
    '2026-04', [], [555 => \$afast], [], []
);
echo 'dias_abonados=' . \$ctx->diasAbonadosNoMes(555);
"
```
Esperado: `dias_abonados=5`. **Bate GAP-MF-02.**

### Validação 5 — Cálculo de folha real para 1 servidor (end-to-end)

```powershell
php artisan tinker --execute="
\$folha = \App\Models\Folha::first();
\$func = \App\Models\Funcionario::first();
if (!\$folha || !\$func) { echo 'sem_dados'; exit; }

\$ids = [\$func->FUNCIONARIO_ID];
\$ctx = \App\Services\MotorFolhaService::prepararContextoLote(\$folha->FOLHA_ID, \$ids);

// Verificar que o contexto traz datas contratuais
echo 'razao=' . \$ctx->razaoProporcionalVencimento(\$func->FUNCIONARIO_ID) . PHP_EOL;
echo 'dias_no_mes=' . \$ctx->diasNoMesCompetencia() . PHP_EOL;
echo 'dias_abonados=' . \$ctx->diasAbonadosNoMes(\$func->FUNCIONARIO_ID) . PHP_EOL;

// Rodar o motor
\$svc = app(\App\Services\MotorFolhaService::class);
\$out = \$svc->calcularLoteParaFuncionarios(\$folha->FOLHA_ID, \$ids, \$ctx);
echo 'motor_ok=' . (\$out['ok'] ?? false ? 'sim' : 'nao') . PHP_EOL;
echo 'servidores=' . (\$out['servidores'] ?? 0) . PHP_EOL;
echo 'total_proventos=' . (\$out['total_proventos'] ?? 0) . PHP_EOL;
echo 'total_descontos=' . (\$out['total_descontos'] ?? 0) . PHP_EOL;
echo 'total_liquido=' . (\$out['total_liquido'] ?? 0);
"
```
Esperado: motor roda sem exceção, retorna `motor_ok=sim`. Os valores numéricos podem variar — só verificar que NÃO são todos zero (a menos que o servidor de teste seja recém-admitido e razão=0).

### Validação 6 — Logs do MotorFolha presentes
```powershell
Get-Content "storage/logs/laravel.log" -Tail 100 | Select-String "\[MotorFolha\]" | Select-Object -Last 5
```
Esperado: pelo menos 1 linha com `[MotorFolha] cálculo lote` mostrando `razao_proporcional`, `dias_abonados`, `venc_base_integral`, `venc_base_proporcional`, `bruto`, `liquido`.

### Validação 7 — Nenhum strftime/julianday em código novo
```powershell
Get-ChildItem -Path "app/Services/Folha", "app/Services/MotorFolha" -Recurse -Filter "*.php" | Select-String -Pattern "strftime|julianday" | Measure-Object | Select-Object -ExpandProperty Count
```
Esperado: `0`.

### Validação 8 — Git log das correções
```powershell
git log --oneline -n 5
```
Esperado: 2 commits novos (`feat(GAP-MF-01,02,03): ...` e `feat(GAP-MF-04): ...`).

---

## REPORT TEMPLATE — preencha e devolva ao Ronaldo/Claude

```
═══════════════════════════════════════════════════════════════════
FASE 2-A — REPORT EXECUÇÃO ANTYGRAVITY (data/hora: ____)
═══════════════════════════════════════════════════════════════════

CORREÇÕES (cole hash do commit):
[ ] T2A.1+2A.2+2A.3 GAP-MF-01,02,03 (pró-rata, abono, dias do mês) ... commit: ____
[ ] T2A.4 InclusaoHorasExtrasService criado ........................ N/A (incluso no commit abaixo)
[ ] T2A.5 RubricasHePlantaoSeeder rodado ........................... output: ____
[ ] T2A.6 GAP-MF-04 integrado no MotorFolhaService ................. commit: ____

VALIDAÇÕES (cole saídas reais):

V1 fev_dias=___ (esperado 28)

V2 razao admissão dia 16=___ (esperado 0.5)

V3 razao exoneração dia 10=___ (esperado ~0.3333)

V4 dias_abonados (LM 10-14/04)=___ (esperado 5)

V5 motor_ok=___ servidores=___ proventos=___ descontos=___ liquido=___

V6 logs [MotorFolha] (cole 5 últimas linhas):
   ___

V7 strftime/julianday em código novo: ___ (esperado 0)

V8 git log -n 5:
   ___

BUGS ENCONTRADOS DURANTE EXECUÇÃO (não estavam na lista):
   ___

PROBLEMAS / DECISÕES TOMADAS QUE PRECISAM DE CONFIRMAÇÃO:
   ___

TEMPO TOTAL REAL: ___h ___min
═══════════════════════════════════════════════════════════════════
```

---

**Próximo briefing:** Fase 2-B — gaps demais (GAP-MF-05 jornada financeira informal, GAP-MF-06 cal_days_in_month em todas as proporcionais, GAP-MF-07 persistência por rubrica EVENTO_DETALHE_FOLHA, GAP-MF-08 R51 INSS RGPS 2025 movido para TabelasImpostoService). Aguardar Claude auditar este report antes de iniciar.

**FIM DO PROMPT FASE 2-A.**
