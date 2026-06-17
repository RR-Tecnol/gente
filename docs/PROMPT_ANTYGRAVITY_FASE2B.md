# PROMPT ANTYGRAVITY — FASE 2-B GENTE v3 (Ampliação MotorFolha — gaps demais)

> **Cole este prompt no Antygravity (Cursor/Gemini) APENAS após Claude auditar e aprovar o report da Fase 2-A.**
> Estimativa total: ~3h Antygravity (auditoria Claude separada: ~1h)
> Pré-condição: Fase 2-A mergeada (commits `feat(GAP-MF-01,02,03)` e `feat(GAP-MF-04)` aprovados).

---

## CONTEXTO DA FASE 2-B

Esta fase fecha os 4 gaps restantes do MotorFolhaService:

- **GAP-MF-05**: Jornada financeira informal (com audit trail F4)
- **GAP-MF-06**: Confirmar `cal_days_in_month` em todos os pontos críticos (parcialmente coberto pela 2-A)
- **GAP-MF-07**: Persistência granular por rubrica em EVENTO + EVENTO_DETALHE_FOLHA
- **GAP-MF-08**: R51 INSS RGPS faixas 2026 + dedução IRRF dependente correta + centralização em TabelasImpostoService

**Após esta fase:** o MotorFolhaService cobrirá 100% das funcionalidades antes distribuídas em 3 motores (MotorFolha + FolhaParser + sp_gera_folha). Fase 3 (aposentar legados) liberada.

### Princípios de design

1. **Não criar migrations novas.** A coluna `JORNADA_FINANCEIRA_HORAS` já existe (migration `2026_03_30_000020_add_colunas_ponto_config_funcionario.php`). Tabelas EVENTO + EVENTO_DETALHE_FOLHA também já existem.
2. **Reutilizar TabelasImpostoService como autoridade única** para INSS RGPS / RPPS / IRRF (já existe — vamos atualizar tabelas e fazer o MotorFolhaService delegar).
3. **F4 audit trail OBRIGATÓRIO em GAP-MF-05.** Usar `\App\Models\AuditLogModel`.
4. **NÃO mexer em routes/web.php.**
5. **Dois commits separados:** GAP-MF-05+06+08 num commit (mudanças cirúrgicas pequenas) e GAP-MF-07 num commit separado (mudança maior, vale isolar).

---

## REGRAS CRÍTICAS DE EXECUÇÃO

1. **Ordem obrigatória:** GAP-08 (atualizar TabelasImpostoService) → GAP-05 (jornada financeira) → GAP-06 (validação) → GAP-07 (persistência rubricas).
2. **Validar via leitura textual e PHP -l** (mesma estratégia da Fase 1 e 2-A — PHP 8.1 não roda artisan, mas `php -l` valida sintaxe).
3. **NÃO usar strftime/julianday/crase em queries** em código novo.
4. **Backups automáticos via git:** cada commit é restaurável.

---

## GAP-MF-08 — Atualizar TabelasImpostoService para 2026 e centralizar (~30 min)

**Por que primeiro:** GAP-MF-05 e GAP-MF-07 vão precisar dessa autoridade central. Ordem importa.

### TAREFA 2B.1 — Atualizar TabelasImpostoService.php

**Arquivo:** `app/Services/TabelasImpostoService.php`

**Mudanças:**

#### 2B.1.a — Atualizar tabela INSS RGPS para faixas 2026

**Trecho atual (linhas ~17-23):**

```php
    // ── INSS RGPS 2024 (alíquotas progressivas — DOU 29/12/2023) ─────────────
    private const INSS_RGPS = [
        // [limite superior, alíquota, parcela a deduzir]
        [1412.00, 0.075, 0.00],
        [2666.68, 0.09, 21.18],
        [4000.03, 0.12, 101.18],
        [7786.02, 0.14, 181.18],
    ];
```

**Trecho corrigido:**

```php
    // ── INSS RGPS 2026 (alíquotas progressivas — Portaria Interministerial MPS/MF nº 13, de 09/01/2026) ─────────────
    // Faixas oficiais:
    //   até R$ 1.621,00         → 7,5%
    //   R$ 1.621,01 a 2.902,84  → 9%
    //   R$ 2.902,85 a 4.354,27  → 12%
    //   R$ 4.354,28 a 8.475,55  → 14% (teto)
    private const INSS_RGPS = [
        // [limite superior, alíquota, parcela a deduzir (calculada para o regime simplificado)]
        [1621.00, 0.075, 0.00],
        [2902.84, 0.09, 24.32],   // (2902,84 × 0,09) − (1621,00 × 0,075) acumulado
        [4354.27, 0.12, 111.40],
        [8475.55, 0.14, 198.49],
    ];

    // Teto INSS RGPS 2026
    private const INSS_RGPS_TETO = 8475.55;
```

**Atenção:** os valores de "parcela a deduzir" são derivados — o cálculo no método `calcularInssRgps` é faixa-a-faixa, então as parcelas a deduzir não são usadas no cálculo. **MAS** documentamos para auditoria humana e referência fiscal.

#### 2B.1.b — Atualizar dedução por dependente IRRF

**Trecho atual (linha ~37):**

```php
    private const DEDUCAO_DEPENDENTE = 226.86;  // por dependente, 2025
```

**Trecho corrigido:**

```php
    // Dedução por dependente IRRF mensal: R$ 189,59 (mantida desde 2024 — IN RFB 2.020/2021).
    // Atenção: O valor R$ 226,86 que aparecia anteriormente NÃO é dedução por dependente — pode ter
    // sido confusão com desconto simplificado mensal (R$ 564,80) ou erro de transcrição. Corrigido.
    private const DEDUCAO_DEPENDENTE = 189.59;
```

#### 2B.1.c — Atualizar teto no método `calcularInssRgps`

**Trecho atual (linhas ~58-78 dentro do método):**

```php
    public function calcularInssRgps(float $salarioBruto): float
    {
        $desconto = 0.0;
        $baseRestante = $salarioBruto;
        $faixaAnterior = 0.0;

        foreach (self::INSS_RGPS as [$teto, $aliquota, $_]) {
            if ($baseRestante <= 0)
                break;

            $faixaTeto = min($teto, $salarioBruto);
            $baseNaFaixa = max(0, $faixaTeto - $faixaAnterior);

            $desconto += $baseNaFaixa * $aliquota;
            $faixaAnterior = $teto;

            if ($salarioBruto <= $teto)
                break;
        }

        // Teto do salário de contribuição RGPS 2024: R$ 7.786,02
        return round(min($desconto, 7786.02 * 0.14), 2);
    }
```

**Trecho corrigido:**

```php
    public function calcularInssRgps(float $salarioBruto): float
    {
        $desconto = 0.0;
        $baseRestante = $salarioBruto;
        $faixaAnterior = 0.0;

        foreach (self::INSS_RGPS as [$teto, $aliquota, $_]) {
            if ($baseRestante <= 0)
                break;

            $faixaTeto = min($teto, $salarioBruto);
            $baseNaFaixa = max(0, $faixaTeto - $faixaAnterior);

            $desconto += $baseNaFaixa * $aliquota;
            $faixaAnterior = $teto;

            if ($salarioBruto <= $teto)
                break;
        }

        // Teto INSS RGPS 2026: R$ 8.475,55 × 14% ≈ R$ 1.186,58 (limite efetivo de desconto)
        return round(min($desconto, self::INSS_RGPS_TETO * 0.14), 2);
    }
```

**Validação 2B.1:**

```powershell
php -l app/Services/TabelasImpostoService.php
Select-String -Path "app/Services/TabelasImpostoService.php" -Pattern "1412\.00|7786\.02|226\.86"
```
Saída esperada `Select-String`: 0 ocorrências (todas substituídas).

```powershell
# Smoke check de cálculo INSS — vai requerer PHP 8.4 para rodar; em PHP 8.1 só validar sintaxe
# Validação manual: trabalhador com R$ 3.000,00 deve ter INSS = R$ 248,60 ± 0,02
# (faixa 1: 1621,00 × 7,5% = 121,575 + faixa 2: 1281,84 × 9% = 115,3656 + faixa 3: 97,16 × 12% = 11,6592 = 248,60)
```

**Commit (parcial — 1 dos 3 desta tarefa):** ainda NÃO commit aqui, vamos junto com GAP-05 + GAP-06.

---

## GAP-MF-06 — Validar cal_days_in_month em todos os pontos (~10 min)

### TAREFA 2B.2 — Varredura cirúrgica de divisões por dias

**Pesquisar todas as referências a "30" como divisor ou multiplicador no MotorFolhaService:**

```powershell
Select-String -Path "app/Services/MotorFolhaService.php" -Pattern "/ ?30[^0-9]|30 ?\*|/ ?diasMes"
```

**Esperado:** 0 ocorrências (a Fase 2-A já cobriu via `MotorFolhaLoteContext::diasNoMesCompetencia()`).

**Se houver match não-trivial**, reportar a Claude antes de mexer (pode ser cálculo legítimo não relacionado a dias-mês).

**Pesquisar referências a "30" em outros services de folha:**

```powershell
Get-ChildItem -Path "app/Services" -Recurse -Filter "*.php" | Select-String -Pattern "diasNoMes|cal_days_in_month|/ ?30[^0-9]" | Where-Object { $_.Path -notmatch "FolhaParserService" }
```

**Saída esperada:** linhas de comentário/documentação OU referências legítimas à `MotorFolhaLoteContext::diasNoMesCompetencia()` (após GAP-MF-01/03 da Fase 2-A) OU vazio.

### TAREFA 2B.3 — Documentar que GAP-MF-06 está concluído

Ainda não fazer commit. Apenas registrar a verificação no report final da Fase 2-B.

---

## GAP-MF-05 — Jornada financeira informal com audit trail F4 (~1h)

**Princípio:** o servidor com `JORNADA_FINANCEIRA_HORAS IS NOT NULL` em `PONTO_CONFIG_FUNCIONARIO` tem **acordo informal de jornada reduzida com salário cheio**. O motor **não desconta** o vencimento (pagar cheio é o acordo), mas registra cada aplicação em audit chain F4 para que TCE-MA tenha rastreabilidade.

### TAREFA 2B.4 — Estender MotorFolhaLoteContext com jornada financeira

**Arquivo:** `app/Services/MotorFolha/MotorFolhaLoteContext.php`

**Edit 1 — adicionar propriedade (após `$datasContratuaisPorFuncionario` adicionada na Fase 2-A):**

Trecho atual (linhas ~28-31):
```php
    /** @var array<int, array{inicio: ?string, fim: ?string}> Datas de admissão/exoneração por funcionário (GAP-MF-03) */
    private array $datasContratuaisPorFuncionario = [];
```

Trecho corrigido (adicionar após):
```php
    /** @var array<int, array{inicio: ?string, fim: ?string}> Datas de admissão/exoneração por funcionário (GAP-MF-03) */
    private array $datasContratuaisPorFuncionario = [];

    /** @var array<int, array{horas: float, obs: ?string}> Jornada financeira informal por funcionário (GAP-MF-05) */
    private array $jornadaFinanceiraPorFuncionario = [];
```

**Edit 2 — atualizar construtor para receber jornada financeira:**

Trecho atual (final do construtor):
```php
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

Trecho corrigido:
```php
    /**
     * @param  array<int, float>  $cargoSalarioPorFuncionario
     * @param  array<int, Collection>  $afastamentosPorFuncionario
     * @param  array<int, Collection>  $avaliacoesPorFuncionario
     * @param  array<int, array{inicio: ?string, fim: ?string}>  $datasContratuaisPorFuncionario
     * @param  array<int, array{horas: float, obs: ?string}>  $jornadaFinanceiraPorFuncionario
     */
    public function __construct(
        string $competenciaYm,
        array $cargoSalarioPorFuncionario,
        array $afastamentosPorFuncionario,
        array $avaliacoesPorFuncionario,
        array $datasContratuaisPorFuncionario = [],
        array $jornadaFinanceiraPorFuncionario = []
    ) {
        $this->competenciaYm = substr($competenciaYm, 0, 7);
        $this->competenciaInicio = Carbon::parse($this->competenciaYm . '-01')->startOfDay();
        $this->competenciaFim = $this->competenciaInicio->copy()->endOfMonth()->endOfDay();
        $this->cargoSalarioPorFuncionario = $cargoSalarioPorFuncionario;
        $this->afastamentosPorFuncionario = $afastamentosPorFuncionario;
        $this->avaliacoesPorFuncionario = $avaliacoesPorFuncionario;
        $this->datasContratuaisPorFuncionario = $datasContratuaisPorFuncionario;
        $this->jornadaFinanceiraPorFuncionario = $jornadaFinanceiraPorFuncionario;
    }
```

**Edit 3 — adicionar 2 novos métodos públicos no fim da classe (antes do `}` final):**

Adicionar após o método `razaoProporcionalVencimento`:

```php

    // ═══════════════════════════════════════════════════════════════════════════
    // GAP-MF-05 — Jornada financeira informal
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Retorna a jornada financeira informal configurada para o funcionário, ou null se não houver.
     *
     * @return array{horas: float, obs: ?string}|null
     */
    public function jornadaFinanceiraInformal(int $funcionarioId): ?array
    {
        return $this->jornadaFinanceiraPorFuncionario[$funcionarioId] ?? null;
    }

    /**
     * Indica se o funcionário tem acordo informal de jornada financeira (jornada reduzida com salário cheio).
     */
    public function temJornadaFinanceiraInformal(int $funcionarioId): bool
    {
        $jf = $this->jornadaFinanceiraInformal($funcionarioId);

        return $jf !== null && (float) $jf['horas'] > 0;
    }
```

### TAREFA 2B.5 — Atualizar prepararContextoLote para popular jornada financeira

**Arquivo:** `app/Services/MotorFolhaService.php`

**Localização:** método `prepararContextoLote`, ~final, antes do `return new MotorFolhaLoteContext(...)`.

**Trecho atual (final do método, linhas ~138-152):**

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

        // GAP-MF-05: pré-carregar jornada financeira informal (1 query por lote, sem N+1)
        $jornadaFinanceiraMap = [];
        if (Schema::hasTable('PONTO_CONFIG_FUNCIONARIO') && Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'JORNADA_FINANCEIRA_HORAS')) {
            $temObs = Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'JORNADA_FINANCEIRA_OBS');
            $cols = ['FUNCIONARIO_ID', 'JORNADA_FINANCEIRA_HORAS'];
            if ($temObs) {
                $cols[] = 'JORNADA_FINANCEIRA_OBS';
            }
            $jornadas = DB::table('PONTO_CONFIG_FUNCIONARIO')
                ->whereIn('FUNCIONARIO_ID', $ids)
                ->whereNotNull('JORNADA_FINANCEIRA_HORAS')
                ->select($cols)
                ->get();
            foreach ($jornadas as $jf) {
                $horas = (float) ($jf->JORNADA_FINANCEIRA_HORAS ?? 0);
                if ($horas > 0) {
                    $jornadaFinanceiraMap[(int) $jf->FUNCIONARIO_ID] = [
                        'horas' => $horas,
                        'obs' => $temObs ? ($jf->JORNADA_FINANCEIRA_OBS ?? null) : null,
                    ];
                }
            }
        }

        return new MotorFolhaLoteContext(
            $competencia,
            $cargoSalario,
            $afastMap,
            $avalMap,
            $datasContratuais,
            $jornadaFinanceiraMap
        );
    }
```

### TAREFA 2B.6 — Audit trail F4 quando jornada financeira é aplicada

**Arquivo:** `app/Services/MotorFolhaService.php`

**Localização:** método `calcularLoteParaFuncionarios`, dentro do `foreach ($servidores as $funcId => $s)`, **logo após** `$diasAbonados = $contexto->diasAbonadosNoMes($funcId);`.

**Trecho atual (linhas ~330-345):**

```php
            // GAP-MF-01/03: aplicar pró-rata por dias contratuais no mês (admissão/exoneração).
            // GAP-MF-06 (parcial): denominador = dias reais do mês de competência (28/29/30/31).
            $razao = $contexto->razaoProporcionalVencimento($funcId);
            $vencBase = $vencBaseIntegral * $razao;

            // GAP-MF-02: dias abonados (LM/LMA/etc.) — informativo, já contabilizados em dias trabalhados.
            $diasAbonados = $contexto->diasAbonadosNoMes($funcId);

            // Dados injectados (sem query): competência × afastamento / desempenho
            $contexto->possuiAfastamentoSobrepostoNaCompetencia($funcId);
```

**Trecho corrigido:**

```php
            // GAP-MF-01/03: aplicar pró-rata por dias contratuais no mês (admissão/exoneração).
            // GAP-MF-06 (parcial): denominador = dias reais do mês de competência (28/29/30/31).
            $razao = $contexto->razaoProporcionalVencimento($funcId);
            $vencBase = $vencBaseIntegral * $razao;

            // GAP-MF-02: dias abonados (LM/LMA/etc.) — informativo, já contabilizados em dias trabalhados.
            $diasAbonados = $contexto->diasAbonadosNoMes($funcId);

            // GAP-MF-05: detectar jornada financeira informal e registrar em audit chain F4.
            // Acordo informal: servidor trabalha menos horas mas recebe salário cheio.
            // O motor NÃO desconta — apenas registra para rastreabilidade TCE-MA.
            //
            // Schema REAL da tabela AUDIT_LOG (auditado por Claude via MCP):
            //   id, USUARIO_ID, ACAO (string 255), TABELA (string 120),
            //   DADOS_ANTIGOS (text), DADOS_NOVOS (text), IP (45), USER_AGENT (255),
            //   HASH_CONCAT (preenchido AUTO pelo booted::creating do model), timestamps.
            //
            // O AuditLogModel é Eloquent puro (sem método estático custom). Use create([...]).
            // Imutabilidade: AUDIT_LOG não permite UPDATE nem DELETE (Frente 3/4 do hardening).
            if ($contexto->temJornadaFinanceiraInformal($funcId)) {
                $jf = $contexto->jornadaFinanceiraInformal($funcId);
                try {
                    $req = request();
                    \App\Models\AuditLogModel::create([
                        'USUARIO_ID' => \Illuminate\Support\Facades\Auth::id(),
                        'ACAO' => 'motor_folha.jornada_financeira_aplicada',
                        'TABELA' => 'PONTO_CONFIG_FUNCIONARIO',
                        'DADOS_ANTIGOS' => null,
                        'DADOS_NOVOS' => json_encode([
                            'folha_id' => $folhaId,
                            'funcionario_id' => $funcId,
                            'jornada_horas' => $jf['horas'] ?? null,
                            'jornada_obs' => $jf['obs'] ?? null,
                            'venc_base_integral' => round($vencBaseIntegral, 2),
                            'venc_base_aplicado' => round($vencBase, 2),
                            'competencia' => $competencia,
                        ], JSON_UNESCAPED_UNICODE),
                        'IP' => $req?->ip(),
                        'USER_AGENT' => substr((string) ($req?->userAgent() ?? ''), 0, 255),
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[MotorFolha] falha ao registrar audit GAP-MF-05', [
                        'funcionario_id' => $funcId,
                        'erro' => $e->getMessage(),
                    ]);
                    // Não fail-fast: motor segue calculando.
                }

                \Illuminate\Support\Facades\Log::info('[MotorFolha][GAP-MF-05] jornada financeira informal aplicada', [
                    'folha_id' => $folhaId,
                    'funcionario_id' => $funcId,
                    'jornada_horas' => $jf['horas'] ?? null,
                ]);
            }

            // Dados injectados (sem query): competência × afastamento / desempenho
            $contexto->possuiAfastamentoSobrepostoNaCompetencia($funcId);
```

**Atenção:** os campos `USUARIO_ID`, `ACAO`, `TABELA`, `DADOS_ANTIGOS`, `DADOS_NOVOS`, `IP`, `USER_AGENT` foram **confirmados via MCP** por Claude (auditoria do schema real em `database/migrations/2026_04_28_131000_create_audit_log_table_if_missing.php` e `2026_04_28_220000_add_audit_log_hash_concat.php`). O `HASH_CONCAT` é preenchido automaticamente pelo `booted::creating` do `AuditLogModel`.

**Antygravity NÃO precisa inventar a assinatura.** Use exatamente o `create([...])` mostrado no código acima.

**Caso surja erro de "column not found":**
```powershell
Get-ChildItem -Path "database/migrations" -Filter "*audit*" | Select-Object Name
```
Se o erro persistir mesmo com os campos certos, **PARAR e reportar a Claude** — pode ser um schema raso em SQLite dev.

### TAREFA 2B.7 — Integrar GAP-08 no MotorFolhaService (delegação para TabelasImpostoService)

**Arquivo:** `app/Services/MotorFolhaService.php`

**Localização:** método `calcularInssRgps` (linha ~518) — vamos delegar para o serviço.

**Trecho atual:**

```php
    private function calcularInssRgps(float $base): float
    {
        $faixas = [
            [1518.00, 0.075],
            [2666.68, 0.09],
            [4000.03, 0.12],
            [7786.02, 0.14],
        ];
        $desconto = 0.0;
        $anterior = 0.0;
        foreach ($faixas as [$teto, $aliq]) {
            if ($base <= $anterior) {
                break;
            }
            $faixa = min($base, $teto) - $anterior;
            $desconto += $faixa * $aliq;
            $anterior = $teto;
            if ($base <= $teto) {
                break;
            }
        }

        return round(min($desconto, $base * 0.14), 2);
    }
```

**Trecho corrigido:**

```php
    /**
     * GAP-MF-08: delegação para TabelasImpostoService (autoridade única de tabelas fiscais 2026).
     */
    private function calcularInssRgps(float $base): float
    {
        return app(\App\Services\TabelasImpostoService::class)->calcularInssRgps($base);
    }
```

**Localização 2:** método `calcularIrrf` (linha ~542) — também delegar.

**Trecho atual:**

```php
    private function calcularIrrf(float $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }
        if ($base <= 2824.00) {
            return 0.0;
        }
        if ($base <= 3751.05) {
            return round($base * 0.075 - 211.80, 2);
        }
        if ($base <= 4664.68) {
            return round($base * 0.15 - 493.05, 2);
        }
        if ($base <= 7083.49) {
            return round($base * 0.225 - 843.16, 2);
        }

        return round($base * 0.275 - 1197.58, 2);
    }
```

**Trecho corrigido:**

```php
    /**
     * GAP-MF-08: delegação para TabelasImpostoService (autoridade única de tabelas fiscais 2026).
     * Nota: a base já inclui dedução INSS; dependentes são deduzidos pelo TabelasImpostoService.
     */
    private function calcularIrrf(float $base): float
    {
        // O motor já desconta dependentes ANTES de chamar este método (linha onde calcula $baseIrrf).
        // Para evitar dupla dedução, passamos 0 dependentes aqui — o cálculo permanece correto.
        return app(\App\Services\TabelasImpostoService::class)->calcularIrrf($base, 0);
    }
```

**Atenção crítica:** o motor faz `$baseIrrf = $bruto - $descPrev - ($dep * 226.86)` ANTES de chamar `calcularIrrf`. Como o `$dep * 226.86` está com **constante hardcoded errada (226.86)**, isso também precisa ser corrigido. **Edit 3:** trocar essa linha.

**Localização 3:** método `calcularLoteParaFuncionarios`, dentro do foreach (~linha 477):

**Trecho atual:**

```php
            $dep = (int) ($s->PESSOA_DEPENDENTES_IRRF ?? 0);
            $baseIrrf = $bruto - $descPrev - ($dep * 226.86);
            $descIRRF = ($s->VINCULO_IRRF ?? true) ? $this->calcularIrrf($baseIrrf) : 0.0;
```

**Trecho corrigido:**

```php
            $dep = (int) ($s->PESSOA_DEPENDENTES_IRRF ?? 0);
            // GAP-MF-08: usar dedução IRRF dependente correta 2026 (R$ 189,59) via TabelasImpostoService.
            // Trocamos o cálculo manual por delegação ao serviço, que já trata dependente internamente.
            $tabelas = app(\App\Services\TabelasImpostoService::class);
            $baseIrrf = $bruto - $descPrev; // dependentes deduzidos dentro do tabelas service
            $descIRRF = ($s->VINCULO_IRRF ?? true) ? $tabelas->calcularIrrf($baseIrrf, $dep) : 0.0;
```

**Atenção:** com essa mudança, a chamada `$this->calcularIrrf(...)` (sem dep) passa a ser **redundante** — pode ser removida do MotorFolhaService inteira. Mas vamos manter o método deprecado como fallback (com `@deprecated` no docblock) para não quebrar nenhuma chamada externa que possa existir.

**Edit 4:** marcar `calcularIrrf` privado como `@deprecated` (já não usado):

Trecho corrigido FINAL do método `calcularIrrf`:
```php
    /**
     * GAP-MF-08: delegação para TabelasImpostoService (autoridade única de tabelas fiscais 2026).
     *
     * @deprecated A partir de GAP-MF-08, o motor chama TabelasImpostoService diretamente no cálculo
     *             principal (com dependentes). Este método permanece apenas como fallback para callers
     *             externos que possam existir.
     */
    private function calcularIrrf(float $base): float
    {
        return app(\App\Services\TabelasImpostoService::class)->calcularIrrf($base, 0);
    }
```

### Validações finais GAP-05/06/08:

```powershell
php -l app/Services/TabelasImpostoService.php
php -l app/Services/MotorFolhaService.php
php -l app/Services/MotorFolha/MotorFolhaLoteContext.php

# Confirmar que faixas antigas foram removidas
Select-String -Path "app/Services" -Recurse -Pattern "1412\.00|7786\.02|226\.86" | Where-Object { $_.Path -notmatch "FolhaParserService" }
# Esperado: 0 ocorrências (com a possível exceção de comentários explicativos do tipo "antes era 226.86")

# Confirmar que dedução IRRF correta está em uso
Select-String -Path "app/Services" -Recurse -Pattern "189\.59"
# Esperado: pelo menos 1 ocorrência em TabelasImpostoService.php
```

**Commit (1 dos 2 desta fase):** `feat(GAP-MF-05,06,08): jornada financeira F4 audit + tabelas 2026 + delegação TabelasImpostoService`


---

## GAP-MF-07 — Persistência granular por rubrica em EVENTO + EVENTO_DETALHE_FOLHA (~1h30)

**Objetivo:** após persistir cada DETALHE_FOLHA, gerar 1 registro `EVENTO_DETALHE_FOLHA` por componente do cálculo (vencimento, anuênio, cada adicional C2, cada lançamento C3, descontos INSS/IRRF/consig, complemento SM). Isso é **CRÍTICO** para holerite detalhado, DIRF, RAIS, SIOPE e auditoria TCE-MA.

### TAREFA 2B.8 — Criar PersistenciaRubricasService

**Criar arquivo:** `app/Services/Folha/PersistenciaRubricasService.php`

**Conteúdo completo:**

```php
<?php

namespace App\Services\Folha;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-MF-07 — Persistência granular de rubricas em EVENTO_DETALHE_FOLHA.
 *
 * Responsabilidade:
 *   - Para cada DETALHE_FOLHA persistido, gerar 1 EVENTO_DETALHE_FOLHA por componente
 *     (vencimento, anuênio, cada adicional C2, cada lançamento C3, INSS, IRRF, consignações,
 *     complemento SM).
 *
 * Idempotência:
 *   - Antes de inserir, deleta EVENTO_DETALHE_FOLHA prévios por DETALHE_FOLHA_ID.
 *   - Re-execução produz o mesmo conjunto de registros (sem duplicação).
 *
 * Resolução de EVENTO_ID:
 *   - Cache em memória (1 query por descrição por execução).
 *   - Se EVENTO não existe, NÃO cria automaticamente — apenas loga warning e pula a rubrica.
 *     A criação de eventos é responsabilidade de seeders (EventosBaseSeeder), não do motor.
 */
final class PersistenciaRubricasService
{
    /**
     * Descrições padronizadas dos eventos do motor.
     * Estes eventos DEVEM existir na tabela EVENTO (seedados pelo EventosBaseSeeder).
     */
    public const EVENTO_VENCIMENTO_BASE = 'VENCIMENTO BASE';
    public const EVENTO_ANUENIO = 'ANUENIO';
    public const EVENTO_INSS_RPPS = 'INSS RPPS';
    public const EVENTO_INSS_RGPS = 'INSS RGPS';
    public const EVENTO_IRRF = 'IRRF';
    public const EVENTO_CONSIGNACOES = 'CONSIGNACOES';
    public const EVENTO_COMPLEMENTO_SM = 'COMPLEMENTO SALARIO MINIMO';

    /** @var array<string, ?int> */
    private array $cacheEventoIdPorDescricao = [];

    /**
     * Persiste rubricas detalhadas para um lote de DETALHE_FOLHA já persistidos.
     *
     * @param  array<int, array<string, mixed>>  $rubricasPorDetalheFolha
     *         [DETALHE_FOLHA_ID => [['descricao' => string, 'valor' => float], ...]]
     */
    public function persistirRubricasLote(array $rubricasPorDetalheFolha): int
    {
        if (! Schema::hasTable('EVENTO_DETALHE_FOLHA')) {
            Log::warning('[PersistenciaRubricas] Tabela EVENTO_DETALHE_FOLHA não existe — operação ignorada.');
            return 0;
        }

        $detalheFolhaIds = array_keys($rubricasPorDetalheFolha);
        if ($detalheFolhaIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($rubricasPorDetalheFolha, $detalheFolhaIds) {
            // Idempotência: limpar EVENTO_DETALHE_FOLHA prévio dos DETALHE_FOLHA do lote
            DB::table('EVENTO_DETALHE_FOLHA')
                ->whereIn('DETALHE_FOLHA_ID', $detalheFolhaIds)
                ->delete();

            $rows = [];
            $now = now();
            $eventosFaltantes = [];

            foreach ($rubricasPorDetalheFolha as $dfId => $rubricas) {
                foreach ($rubricas as $r) {
                    $descricao = (string) ($r['descricao'] ?? '');
                    $valor = (float) ($r['valor'] ?? 0);
                    if ($descricao === '' || $valor == 0.0) {
                        continue;
                    }

                    $eventoId = $this->resolverEventoIdPorDescricao($descricao);
                    if ($eventoId === null) {
                        $eventosFaltantes[$descricao] = ($eventosFaltantes[$descricao] ?? 0) + 1;
                        continue;
                    }

                    $rows[] = [
                        'EVENTO_ID' => $eventoId,
                        'DETALHE_FOLHA_ID' => (int) $dfId,
                        'EVENTO_DETALHE_FOLHA_VALOR' => round($valor, 2),
                    ];
                }
            }

            if ($rows !== []) {
                // Insert em chunks para não estourar limites do driver
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('EVENTO_DETALHE_FOLHA')->insert($chunk);
                }
            }

            if ($eventosFaltantes !== []) {
                Log::warning('[PersistenciaRubricas] Eventos não encontrados na tabela EVENTO — rubricas ignoradas.', [
                    'eventos_faltantes' => $eventosFaltantes,
                    'sugestao' => 'Rodar seeder EventosBaseSeeder para criar.',
                ]);
            }

            Log::info('[PersistenciaRubricas] Rubricas persistidas', [
                'detalhe_folha_ids' => count($detalheFolhaIds),
                'rubricas_inseridas' => count($rows),
                'eventos_faltantes' => count($eventosFaltantes),
            ]);

            return count($rows);
        });
    }

    /**
     * Resolve o EVENTO_ID por descrição (com cache memoizado).
     */
    private function resolverEventoIdPorDescricao(string $descricao): ?int
    {
        if (array_key_exists($descricao, $this->cacheEventoIdPorDescricao)) {
            return $this->cacheEventoIdPorDescricao[$descricao];
        }

        $id = DB::table('EVENTO')
            ->where('EVENTO_DESCRICAO', $descricao)
            ->where('EVENTO_ATIVO', 1)
            ->value('EVENTO_ID');

        $this->cacheEventoIdPorDescricao[$descricao] = $id ? (int) $id : null;

        return $this->cacheEventoIdPorDescricao[$descricao];
    }

    /**
     * Resolve o EVENTO_ID de uma rubrica específica (LANCAMENTO_FOLHA / ADICIONAL_SERVIDOR).
     * Diferente de `resolverEventoIdPorDescricao`, este busca via tabela RUBRICA → EVENTO.
     *
     * Estratégia de fallback:
     *   1. Tentar resolver pela RUBRICA_ID (se RUBRICA tiver EVENTO_ID associado)
     *   2. Senão, resolver pela RUBRICA_DESCRICAO usando descrição como chave
     *   3. Se nada bater, retornar null (warning logado)
     */
    public function resolverEventoIdPorRubrica(int $rubricaId, ?string $rubricaDescricao = null): ?int
    {
        // Se RUBRICA tem coluna EVENTO_ID, usar
        if (Schema::hasColumn('RUBRICA', 'EVENTO_ID')) {
            $eventoId = DB::table('RUBRICA')
                ->where('RUBRICA_ID', $rubricaId)
                ->value('EVENTO_ID');
            if ($eventoId) {
                return (int) $eventoId;
            }
        }

        // Fallback: usar descrição da rubrica
        $desc = $rubricaDescricao
            ?? DB::table('RUBRICA')->where('RUBRICA_ID', $rubricaId)->value('RUBRICA_DESCRICAO');

        if ($desc) {
            return $this->resolverEventoIdPorDescricao((string) $desc);
        }

        return null;
    }
}
```

### TAREFA 2B.9 — Criar EventosBaseSeeder

**Criar arquivo:** `database/seeders/EventosBaseSeeder.php`

**Conteúdo:**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed dos eventos básicos usados pelo MotorFolhaService.
 * Idempotente: usa updateOrInsert por EVENTO_DESCRICAO.
 */
class EventosBaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('EVENTO')) {
            $this->command->warn('Tabela EVENTO não existe — seeder ignorado.');
            return;
        }

        $eventos = [
            // Proventos C1
            ['descricao' => 'VENCIMENTO BASE',                'salario' => 1, 'imposto' => 0],
            ['descricao' => 'ANUENIO',                        'salario' => 1, 'imposto' => 0],

            // Descontos previdenciários
            ['descricao' => 'INSS RPPS',                      'salario' => 0, 'imposto' => 1],
            ['descricao' => 'INSS RGPS',                      'salario' => 0, 'imposto' => 1],

            // Imposto de renda
            ['descricao' => 'IRRF',                           'salario' => 0, 'imposto' => 1],

            // Outros descontos
            ['descricao' => 'CONSIGNACOES',                   'salario' => 0, 'imposto' => 0],
            ['descricao' => 'COMPLEMENTO SALARIO MINIMO',     'salario' => 1, 'imposto' => 0],
        ];

        foreach ($eventos as $e) {
            $payload = [
                'EVENTO_SALARIO' => $e['salario'],
                'EVENTO_IMPOSTO' => $e['imposto'],
                'EVENTO_INCIDENCIA' => 0,
                'EVENTO_SISTEMA' => 1,
                'EVENTO_ATIVO' => 1,
            ];

            DB::table('EVENTO')->updateOrInsert(
                ['EVENTO_DESCRICAO' => $e['descricao']],
                $payload
            );
        }

        $this->command->info('EventosBaseSeeder: eventos básicos garantidos.');
    }
}
```

**Importante:** rodar `composer dump-autoload` após criar o seeder se o autoload não detectar automaticamente.

### TAREFA 2B.10 — Integrar PersistenciaRubricasService no MotorFolhaService

**Arquivo:** `app/Services/MotorFolhaService.php`

**Localização:** método `calcularLoteParaFuncionarios`, dentro do `foreach`. Vamos coletar as rubricas em uma estrutura auxiliar e, após persistir DETALHE_FOLHA, chamar `PersistenciaRubricasService`.

**Estratégia:** introduzir variável `$rubricasParaPersistir` antes do foreach, popular durante o cálculo de cada servidor, e processar no fim.

**Edit 1 — antes do foreach (~linha 318):**

Trecho atual:
```php
        $aliqRPPS = $this->resolverAliquotaRpps();
        $salarioMin = self::SALARIO_MIN_2025;

        $resultados = [];

        foreach ($servidores as $funcId => $s) {
```

Trecho corrigido:
```php
        $aliqRPPS = $this->resolverAliquotaRpps();
        $salarioMin = self::SALARIO_MIN_2025;

        $resultados = [];

        // GAP-MF-07: coletar rubricas detalhadas por funcionário durante o cálculo,
        // para persistir em EVENTO_DETALHE_FOLHA após a persistência do agregado.
        // Estrutura: [funcId => [['descricao' => string, 'valor' => float], ...]]
        $rubricasPorFuncionario = [];
        $persistenciaRubricas = app(\App\Services\Folha\PersistenciaRubricasService::class);

        foreach ($servidores as $funcId => $s) {
```

**Edit 2 — popular rubricas C1 (vencimento + anuênio) — após o switch (~linha 405):**

Trecho atual (logo após o switch, antes do C2):
```php
            $provC2 = 0.0;
            $basePrev = $provC1;
            foreach (($adicionais[$funcId] ?? collect()) as $ad) {
```

Trecho corrigido:
```php
            // GAP-MF-07: registrar componentes C1 (vencimento estrutural + anuênio)
            $rubricasPorFuncionario[$funcId] = [];
            if ($vencBase > 0) {
                // O "vencimento base aplicado" já está proporcionalizado por (dias_contratuais / dias_mes)
                $rubricasPorFuncionario[$funcId][] = [
                    'descricao' => \App\Services\Folha\PersistenciaRubricasService::EVENTO_VENCIMENTO_BASE,
                    'valor' => round($vencBase, 2),
                ];
            }
            if (($anuenioVal ?? 0) > 0) {
                $rubricasPorFuncionario[$funcId][] = [
                    'descricao' => \App\Services\Folha\PersistenciaRubricasService::EVENTO_ANUENIO,
                    'valor' => round($anuenioVal, 2),
                ];
            }

            $provC2 = 0.0;
            $basePrev = $provC1;
            foreach (($adicionais[$funcId] ?? collect()) as $ad) {
```

**Edit 3 — popular rubricas C2 dentro do foreach de adicionais:**

Trecho atual:
```php
            foreach (($adicionais[$funcId] ?? collect()) as $ad) {
                $val = match ($ad->ADICIONAL_TIPO) {
                    'fixo' => (float) $ad->ADICIONAL_VALOR,
                    'percentual' => $vencBase * ((float) $ad->ADICIONAL_VALOR / 100),
                    'percentual_sm' => $salarioMin * ((float) $ad->ADICIONAL_VALOR / 100),
                    default => 0.0,
                };
                $provC2 += $val;

                if ($ad->ADICIONAL_INCIDE_PREV) {
                    $basePrev += $val;
                }
            }
```

Trecho corrigido:
```php
            foreach (($adicionais[$funcId] ?? collect()) as $ad) {
                $val = match ($ad->ADICIONAL_TIPO) {
                    'fixo' => (float) $ad->ADICIONAL_VALOR,
                    'percentual' => $vencBase * ((float) $ad->ADICIONAL_VALOR / 100),
                    'percentual_sm' => $salarioMin * ((float) $ad->ADICIONAL_VALOR / 100),
                    default => 0.0,
                };
                $provC2 += $val;

                if ($ad->ADICIONAL_INCIDE_PREV) {
                    $basePrev += $val;
                }

                // GAP-MF-07: registrar adicional C2 — descrição via RUBRICA
                if ($val > 0 && isset($ad->RUBRICA_ID)) {
                    $eventoId = $persistenciaRubricas->resolverEventoIdPorRubrica((int) $ad->RUBRICA_ID);
                    if ($eventoId !== null) {
                        $rubricasPorFuncionario[$funcId][] = [
                            'descricao' => '__POR_EVENTO_ID__:' . $eventoId,
                            'valor' => round($val, 2),
                        ];
                    }
                }
            }
```

**Atenção:** o uso de prefixo `__POR_EVENTO_ID__:` é um workaround para o `PersistenciaRubricasService` que resolve por descrição. Vamos ajustar o serviço para também aceitar EVENTO_ID direto. **Edit 4 (no PersistenciaRubricasService):** adicionar tratamento para o prefixo:

No método `persistirRubricasLote`, **antes** do `$eventoId = $this->resolverEventoIdPorDescricao($descricao);`:

```php
                    // GAP-MF-07: aceitar formato '__POR_EVENTO_ID__:N' (vindo de RUBRICA com EVENTO_ID)
                    if (str_starts_with($descricao, '__POR_EVENTO_ID__:')) {
                        $eventoId = (int) substr($descricao, strlen('__POR_EVENTO_ID__:'));
                        if ($eventoId > 0) {
                            $rows[] = [
                                'EVENTO_ID' => $eventoId,
                                'DETALHE_FOLHA_ID' => (int) $dfId,
                                'EVENTO_DETALHE_FOLHA_VALOR' => round($valor, 2),
                            ];
                            continue;
                        }
                    }

                    $eventoId = $this->resolverEventoIdPorDescricao($descricao);
```

**Edit 5 — popular rubricas C3 (lançamentos) similar ao C2:**

Trecho atual:
```php
            $provC3 = 0.0;
            $descC3 = 0.0;
            foreach (($lancamentos[$funcId] ?? collect()) as $lanc) {
                $total = (float) $lanc->LANCAMENTO_VALOR_TOTAL;
                if ($lanc->LANCAMENTO_TIPO === 'P') {
                    $provC3 += $total;
                    if ($lanc->LANCAMENTO_INCIDE_PREV) {
                        $basePrev += $total;
                    }
                } else {
                    $descC3 += $total;
                }
            }
```

Trecho corrigido:
```php
            $provC3 = 0.0;
            $descC3 = 0.0;
            foreach (($lancamentos[$funcId] ?? collect()) as $lanc) {
                $total = (float) $lanc->LANCAMENTO_VALOR_TOTAL;
                if ($lanc->LANCAMENTO_TIPO === 'P') {
                    $provC3 += $total;
                    if ($lanc->LANCAMENTO_INCIDE_PREV) {
                        $basePrev += $total;
                    }
                } else {
                    $descC3 += $total;
                }

                // GAP-MF-07: registrar lançamento C3 (provento OU desconto)
                if ($total > 0 && isset($lanc->RUBRICA_ID)) {
                    $eventoId = $persistenciaRubricas->resolverEventoIdPorRubrica((int) $lanc->RUBRICA_ID);
                    if ($eventoId !== null) {
                        $rubricasPorFuncionario[$funcId][] = [
                            'descricao' => '__POR_EVENTO_ID__:' . $eventoId,
                            'valor' => round($total, 2),
                        ];
                    }
                }
            }
```

**Edit 6 — popular rubricas de descontos (INSS, IRRF, consignações) e complemento SM:**

**Localização:** no fim do foreach de cada funcionário, **antes** do `$resultados[$funcId] = [...]`.

Trecho atual:
```php
            $descConsig = (float) ($consignacoes[$funcId]->total_consig ?? 0);

            $descOutros = $descC3 + $descConsig;
            $liquido = $bruto - $descPrev - $descIRRF - $descOutros;

            \Illuminate\Support\Facades\Log::info('[MotorFolha] cálculo lote', [
```

Trecho corrigido:
```php
            $descConsig = (float) ($consignacoes[$funcId]->total_consig ?? 0);

            $descOutros = $descC3 + $descConsig;
            $liquido = $bruto - $descPrev - $descIRRF - $descOutros;

            // GAP-MF-07: registrar descontos previdenciários, IRRF, consignações e complemento SM
            if ($descPrev > 0) {
                $regimeRPPS = ($s->VINCULO_REGIME ?? $s->FUNCIONARIO_REGIME_PREV ?? 'RPPS') === 'RPPS';
                $rubricasPorFuncionario[$funcId][] = [
                    'descricao' => $regimeRPPS
                        ? \App\Services\Folha\PersistenciaRubricasService::EVENTO_INSS_RPPS
                        : \App\Services\Folha\PersistenciaRubricasService::EVENTO_INSS_RGPS,
                    'valor' => round($descPrev, 2),
                ];
            }
            if ($descIRRF > 0) {
                $rubricasPorFuncionario[$funcId][] = [
                    'descricao' => \App\Services\Folha\PersistenciaRubricasService::EVENTO_IRRF,
                    'valor' => round($descIRRF, 2),
                ];
            }
            if ($descConsig > 0) {
                $rubricasPorFuncionario[$funcId][] = [
                    'descricao' => \App\Services\Folha\PersistenciaRubricasService::EVENTO_CONSIGNACOES,
                    'valor' => round($descConsig, 2),
                ];
            }
            if ($complementoSM > 0) {
                $rubricasPorFuncionario[$funcId][] = [
                    'descricao' => \App\Services\Folha\PersistenciaRubricasService::EVENTO_COMPLEMENTO_SM,
                    'valor' => round($complementoSM, 2),
                ];
            }

            \Illuminate\Support\Facades\Log::info('[MotorFolha] cálculo lote', [
```

**Edit 7 — após persistir DETALHE_FOLHA, persistir rubricas:**

**Localização:** após `$this->persistirDetalhesLoteEmTransacao($resultados);`

Trecho atual:
```php
        $this->persistirDetalhesLoteEmTransacao($resultados);

        $col = collect($resultados);

        return [
            'ok' => true,
```

Trecho corrigido:
```php
        $this->persistirDetalhesLoteEmTransacao($resultados);

        // GAP-MF-07: persistir EVENTO_DETALHE_FOLHA — precisa dos DETALHE_FOLHA_IDs
        // que acabaram de ser persistidos. Recuperar via consulta usando (FUNCIONARIO_ID, FOLHA_ID).
        try {
            $funcIdsPersistidos = array_keys($resultados);
            $detalheFolhaIdMap = DB::table('DETALHE_FOLHA')
                ->where('FOLHA_ID', $folhaId)
                ->whereIn('FUNCIONARIO_ID', $funcIdsPersistidos)
                ->pluck('DETALHE_FOLHA_ID', 'FUNCIONARIO_ID')
                ->all();

            $rubricasPorDetalheFolha = [];
            foreach ($rubricasPorFuncionario as $funcIdK => $rubricas) {
                $dfId = $detalheFolhaIdMap[$funcIdK] ?? null;
                if ($dfId !== null) {
                    $rubricasPorDetalheFolha[(int) $dfId] = $rubricas;
                }
            }

            if ($rubricasPorDetalheFolha !== []) {
                $persistenciaRubricas->persistirRubricasLote($rubricasPorDetalheFolha);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[MotorFolha][GAP-MF-07] falha ao persistir rubricas', [
                'folha_id' => $folhaId,
                'erro' => $e->getMessage(),
            ]);
            // Não fail-fast: motor já calculou DETALHE_FOLHA com sucesso.
        }

        $col = collect($resultados);

        return [
            'ok' => true,
```

### Validações finais GAP-07:

```powershell
php -l app/Services/Folha/PersistenciaRubricasService.php
php -l app/Services/MotorFolhaService.php
php -l database/seeders/EventosBaseSeeder.php

# Confirmar que o serviço foi criado
Test-Path "app/Services/Folha/PersistenciaRubricasService.php"
# Esperado: True

# Confirmar idempotência via leitura textual: deve haver DELETE prévio + INSERT
Select-String -Path "app/Services/Folha/PersistenciaRubricasService.php" -Pattern "delete\(\)"
# Esperado: 1+ ocorrências
```

**Commit (2 dos 2 desta fase):** `feat(GAP-MF-07): PersistenciaRubricasService + EVENTO_DETALHE_FOLHA granular + EventosBaseSeeder`


---

## VALIDAÇÃO FINAL — Smoke test pós-Fase 2-B

Execute todos os comandos abaixo e cole as saídas no report:

### Validação 1 — Sintaxe PHP de todos os arquivos modificados/criados
```powershell
php -l app/Services/TabelasImpostoService.php
php -l app/Services/MotorFolhaService.php
php -l app/Services/MotorFolha/MotorFolhaLoteContext.php
php -l app/Services/Folha/PersistenciaRubricasService.php
php -l database/seeders/EventosBaseSeeder.php
```
Esperado: `No syntax errors detected` em todos.

### Validação 2 — Tabelas atualizadas para 2026
```powershell
Select-String -Path "app/Services/TabelasImpostoService.php" -Pattern "1621\.00|2902\.84|4354\.27|8475\.55|189\.59"
```
Esperado: cada um aparece pelo menos 1 vez.

### Validação 3 — Faixas antigas removidas
```powershell
Get-ChildItem -Path "app/Services" -Recurse -Filter "*.php" | Select-String -Pattern "1412\.00|7786\.02|226\.86" | Where-Object { $_.Path -notmatch "FolhaParserService" }
```
Esperado: 0 ocorrências (com possível exceção em comentário explicativo de mudança).

### Validação 4 — Confirmar JORNADA_FINANCEIRA presente em MotorFolhaLoteContext
```powershell
Select-String -Path "app/Services/MotorFolha/MotorFolhaLoteContext.php" -Pattern "jornadaFinanceiraInformal|temJornadaFinanceiraInformal|JORNADA_FINANCEIRA_HORAS"
```
Esperado: 3+ ocorrências.

### Validação 5 — Audit trail F4 presente em MotorFolhaService
```powershell
Select-String -Path "app/Services/MotorFolhaService.php" -Pattern "AuditLogModel|jornada_financeira_aplicada|GAP-MF-05"
```
Esperado: 3+ ocorrências.

### Validação 6 — PersistenciaRubricasService criado
```powershell
Test-Path "app/Services/Folha/PersistenciaRubricasService.php"
Select-String -Path "app/Services/Folha/PersistenciaRubricasService.php" -Pattern "EVENTO_VENCIMENTO_BASE|persistirRubricasLote|resolverEventoIdPorRubrica"
```
Esperado: `True` + 3+ ocorrências.

### Validação 7 — EventosBaseSeeder criado
```powershell
Test-Path "database/seeders/EventosBaseSeeder.php"
Select-String -Path "database/seeders/EventosBaseSeeder.php" -Pattern "VENCIMENTO BASE|ANUENIO|INSS RPPS|IRRF|updateOrInsert"
```
Esperado: `True` + 5+ ocorrências.

### Validação 8 — Integração GAP-07 no MotorFolhaService
```powershell
Select-String -Path "app/Services/MotorFolhaService.php" -Pattern "PersistenciaRubricasService|rubricasPorFuncionario|GAP-MF-07"
```
Esperado: 5+ ocorrências.

### Validação 9 — Sem strftime/julianday/crase em código novo
```powershell
Get-ChildItem -Path "app/Services/Folha", "app/Services/MotorFolha" -Recurse -Filter "*.php" | Select-String -Pattern "strftime|julianday|\`[A-Z_]+\`"
```
Esperado: 0 ocorrências.

### Validação 10 — Git log das correções
```powershell
git log --oneline -n 5
```
Esperado: 2 commits novos:
- `feat(GAP-MF-05,06,08): jornada financeira F4 audit + tabelas 2026 + delegação TabelasImpostoService`
- `feat(GAP-MF-07): PersistenciaRubricasService + EVENTO_DETALHE_FOLHA granular + EventosBaseSeeder`

---

## REPORT TEMPLATE — preencha e devolva ao Ronaldo/Claude

```
═══════════════════════════════════════════════════════════════════
FASE 2-B — REPORT EXECUÇÃO ANTYGRAVITY (data/hora: ____)
═══════════════════════════════════════════════════════════════════

CORREÇÕES (cole hash do commit):
[ ] T2B.1 GAP-MF-08 TabelasImpostoService 2026 ........... commit: ____
[ ] T2B.4-2B.6 GAP-MF-05 jornada financeira F4 ........... (mesmo commit acima)
[ ] T2B.2-2B.3 GAP-MF-06 cal_days_in_month varredura ..... (mesmo commit acima)
[ ] T2B.7 GAP-MF-08 delegação no MotorFolhaService ....... (mesmo commit acima)
[ ] T2B.8 PersistenciaRubricasService criado ............. commit: ____
[ ] T2B.9 EventosBaseSeeder criado ....................... (mesmo commit acima)
[ ] T2B.10 GAP-MF-07 integração no MotorFolhaService ..... (mesmo commit acima)

VALIDAÇÕES (cole saídas reais):

V1 php -l (5 arquivos):
   ___

V2 tabelas 2026 (1621.00/2902.84/4354.27/8475.55/189.59):
   ___ ocorrências (esperado pelo menos 5)

V3 faixas antigas removidas (1412.00/7786.02/226.86):
   ___ ocorrências (esperado 0)

V4 JORNADA_FINANCEIRA em MotorFolhaLoteContext:
   ___ ocorrências

V5 audit trail F4 em MotorFolhaService (AuditLogModel/jornada_financeira_aplicada/GAP-MF-05):
   ___ ocorrências

V6 PersistenciaRubricasService criado:
   Test-Path: ___
   Padrões: ___ ocorrências

V7 EventosBaseSeeder criado:
   Test-Path: ___
   Padrões: ___ ocorrências

V8 Integração GAP-07 (PersistenciaRubricasService/rubricasPorFuncionario/GAP-MF-07):
   ___ ocorrências

V9 strftime/julianday/crase em código novo:
   ___ ocorrências (esperado 0)

V10 git log -n 5:
   ___

ATENÇÃO: SCHEMA AUDIT_LOG (já confirmado por Claude via MCP):
- Os campos REAIS são: USUARIO_ID, ACAO, TABELA, DADOS_ANTIGOS, DADOS_NOVOS, IP, USER_AGENT, HASH_CONCAT (auto)
- HASH_CONCAT é preenchido automaticamente pelo booted::creating do AuditLogModel
- Schema confirmado nas migrations 2026_04_28_131000_create_audit_log_table_if_missing.php e 2026_04_28_220000_add_audit_log_hash_concat.php
- Use exatamente o `create([...])` do prompt — não inventar campos.
- Se ocorrer erro de "column not found": ___

BUGS ENCONTRADOS DURANTE EXECUÇÃO (não estavam na lista):
   ___

PROBLEMAS / DECISÕES TOMADAS QUE PRECISAM DE CONFIRMAÇÃO:
   ___

TEMPO TOTAL REAL: ___h ___min
═══════════════════════════════════════════════════════════════════
```

---

## DEPENDÊNCIA: rodar EventosBaseSeeder + RubricasHePlantaoSeeder

Após o merge da Fase 2-B no servidor com PHP 8.4:

```powershell
php artisan db:seed --class=Database\\Seeders\\EventosBaseSeeder
php artisan db:seed --class=Database\\Seeders\\RubricasHePlantaoSeeder
```

Em PHP 8.1 (máquina de desenvolvimento), seeder não roda — fica para a Fase 6 T03 ETL no servidor PMSL.

---

## ⚠️ NOTA SOBRE A FASE 2-A (descoberta tardia — Claude via MCP)

Durante a auditoria pré-2-B, Claude verificou via MCP o `RubricasCatalogoSeeder.php` (que já existe e cataloga 27 rubricas oficiais do PCCV de São Luís). **Os códigos de rubrica usados pelo `InclusaoHorasExtrasService` da Fase 2-A NÃO BATEM com o catálogo existente:**

| Código no PROMPT FASE 2-A | Código real no catálogo |
|---------------------------|-------------------------|
| `HE_50` | `030` ("Hora Extra 50%") |
| `HE_100` | `031` ("Hora Extra 100%") |
| `HE_FER` | (não existe — usar `031` fallback) |
| `PLANTAO_EXTRA` | `032` ("Plantão Extra") |

**Implicação:** ao rodar o motor após Fase 2-A, o `InclusaoHorasExtrasService::resolverRubricaIdHe()` falhará para registros HE existentes — vai cair no `Log::warning('Rubrica de hora extra não encontrada')` e os HE não serão incluídos como LANCAMENTO_FOLHA.

**Duas soluções possíveis (ESCOLHER UMA):**

### Solução A — Atualizar constantes do InclusaoHorasExtrasService (RECOMENDADA)

Editar `app/Services/Folha/InclusaoHorasExtrasService.php` (criado na Fase 2-A) e trocar as constantes:

```php
private const RUBRICA_HE_50_CODIGO = '030';      // era 'HE_50'
private const RUBRICA_HE_100_CODIGO = '031';     // era 'HE_100'
private const RUBRICA_HE_FERIADO_CODIGO = '031'; // era 'HE_FER' (fallback para 100%)
private const RUBRICA_PLANTAO_CODIGO = '032';    // era 'PLANTAO_EXTRA'
```

E **NÃO RODAR** o `RubricasHePlantaoSeeder` da Fase 2-A (ele duplicaria).

### Solução B — Manter o RubricasHePlantaoSeeder

Manter as constantes `HE_50/HE_100/HE_FER/PLANTAO_EXTRA` e rodar o seeder, que adicionará os códigos como aliases adicionais. Funciona mas duplica códigos no catálogo.

**A Solução A é melhor**, e Antygravity deve aplicá-la **antes** de iniciar a Fase 2-B (não é parte da Fase 2-B, é uma correção retroativa da Fase 2-A).

**Commit (extra):** `fix(GAP-MF-04): usar códigos de rubrica do catálogo PCCV (030/031/032) ao invés de aliases`

---

## PRÓXIMA ETAPA APÓS APROVAÇÃO DA FASE 2-B

**Fase 3 — Aposentar motores legados (FolhaParserService + sp_gera_folha)**

Após Fase 2-B aprovada, o MotorFolhaService cobre 100% das funcionalidades dos motores antigos. A Fase 3 fará:

1. Trocar `FolhaController::inserir` para chamar `MotorFolhaService` ao invés de `FolhaParserService::dispatch`
2. Trocar `FolhaController::alterar` para chamar `MotorFolhaService` ao invés de `Folha::reprocessarFolha` (sp_gera_folha)
3. Mover `FolhaParserService.php` para `app/Services/_legacy/` como backup
4. Marcar `Folha::salvaFolha`, `Folha::processarFolha`, `Folha::reprocessarFolha` como `@deprecated`

Estimativa: ~1h Antygravity. Prompt será gerado quando Fase 2-B for aprovada.

**FIM DO PROMPT FASE 2-B.**
