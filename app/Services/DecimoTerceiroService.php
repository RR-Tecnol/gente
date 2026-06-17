<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-13 — 13º Salário GENTE v3
 *
 * Leis: CF/88 art. 7º VIII, Lei 4.749/65, Lei 4.090/62
 *
 * 1ª Parcela (fev–nov): vencimento_base / 2 — sem INSS, sem IRRF
 * 2ª Parcela (até 20/dez): base integral − INSS − IRRF − adiantamento 1ª parcela
 * Rescisório: base × (meses_trabalhados / 12) − INSS − IRRF
 * Fração de mês ≥ 15 dias = mês cheio (Lei 4.749/65 art. 1º §1º)
 */
class DecimoTerceiroService
{
    private const MESES_ANO = 12;
    private const DIAS_MINIMOS_MES = 15;
    private const SALARIO_MIN = 1518.00;

    public function __construct(
        private TabelasImpostoService $tabelas
    ) {}

    // ── Cálculos individuais ───────────────────────────────────────────────

    public function calcularPrimeiraParcela(int $funcionarioId, int $ano): array
    {
        $vencBase = $this->resolverVencimentoBase($funcionarioId);
        if ($vencBase <= 0) {
            return ['ok' => false, 'erro' => 'Vencimento base não encontrado.', 'funcionario_id' => $funcionarioId];
        }

        $valor = round($vencBase / 2, 2);

        return [
            'ok' => true,
            'funcionario_id' => $funcionarioId,
            'tipo' => 'DECIMO_TERCEIRO_1',
            'ano' => $ano,
            'venc_base' => $vencBase,
            'valor_bruto' => $valor,
            'valor_inss' => 0.0,
            'valor_irrf' => 0.0,
            'valor_liquido' => $valor,
            'descricao' => "13º Salário {$ano} — 1ª Parcela",
        ];
    }

    public function calcularSegundaParcela(int $funcionarioId, int $ano, float $adiantamento = 0.0): array
    {
        $vencBase = $this->resolverVencimentoBase($funcionarioId);
        if ($vencBase <= 0) {
            return ['ok' => false, 'erro' => 'Vencimento base não encontrado.', 'funcionario_id' => $funcionarioId];
        }

        $adicionais = $this->resolverAdicionaisPermanentes($funcionarioId, $vencBase);
        $base = $vencBase + $adicionais;

        $regime = $this->resolverRegime($funcionarioId);
        $aliqRpps = $this->resolverAliquotaRpps();
        $dependentes = $this->resolverDependentes($funcionarioId);

        $inss = ($regime === 'RPPS')
            ? round($base * $aliqRpps, 2)
            : $this->tabelas->calcularInssRgps($base);

        $irrf = $this->tabelas->calcularIrrf($base - $inss, $dependentes);
        $liquido = round($base - $inss - $irrf - $adiantamento, 2);

        return [
            'ok' => true,
            'funcionario_id' => $funcionarioId,
            'tipo' => 'DECIMO_TERCEIRO_2',
            'ano' => $ano,
            'venc_base' => $vencBase,
            'base_calculo' => round($base, 2),
            'valor_bruto' => round($base, 2),
            'valor_inss' => $inss,
            'valor_irrf' => $irrf,
            'adiantamento' => $adiantamento,
            'valor_liquido' => $liquido,
            'descricao' => "13º Salário {$ano} — 2ª Parcela",
        ];
    }

    public function calcularRescisorio(int $funcionarioId, string $dataRescisao, int $ano): array
    {
        $vencBase = $this->resolverVencimentoBase($funcionarioId);
        if ($vencBase <= 0) {
            return ['ok' => false, 'erro' => 'Vencimento base não encontrado.', 'funcionario_id' => $funcionarioId];
        }

        $meses = $this->calcularMesesTrabalhados($dataRescisao, $ano);
        $adicionais = $this->resolverAdicionaisPermanentes($funcionarioId, $vencBase);
        $base = $vencBase + $adicionais;
        $valorProp = round(($base / self::MESES_ANO) * $meses, 2);

        $regime = $this->resolverRegime($funcionarioId);
        $aliqRpps = $this->resolverAliquotaRpps();
        $dependentes = $this->resolverDependentes($funcionarioId);

        $inss = ($regime === 'RPPS')
            ? round($valorProp * $aliqRpps, 2)
            : $this->tabelas->calcularInssRgps($valorProp);

        $irrf = $this->tabelas->calcularIrrf($valorProp - $inss, $dependentes);
        $liquido = round($valorProp - $inss - $irrf, 2);

        return [
            'ok' => true,
            'funcionario_id' => $funcionarioId,
            'tipo' => 'RESCISORIO',
            'ano' => $ano,
            'meses_trabalhados' => $meses,
            'base_calculo' => round($base, 2),
            'valor_proporcional' => $valorProp,
            'valor_inss' => $inss,
            'valor_irrf' => $irrf,
            'valor_liquido' => $liquido,
            'descricao' => "13º Rescisório {$ano} ({$meses}/12 meses)",
        ];
    }

    // ── Processamento em lote (persiste em FOLHA + DETALHE_FOLHA) ─────────

    public function processarLote(string $tipo, int $ano, array $funcionarioIds = []): array
    {
        // FOLHA_COMPETENCIA é INTEGER YYYYMM → filtrar por ano usa divisão inteira
        $competencia = match ($tipo) {
            'DECIMO_TERCEIRO_1' => (int) "{$ano}11",
            'DECIMO_TERCEIRO_2' => (int) "{$ano}12",
            default => (int) "{$ano}12",
        };

        $folhaId = $this->obterOuCriarFolha($tipo, $ano, $competencia);

        if (empty($funcionarioIds)) {
            $q = DB::table('FUNCIONARIO');
            if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
                $q->where('FUNCIONARIO_ATIVO', 1);
            }
            $funcionarioIds = $q->pluck('FUNCIONARIO_ID')->map(fn($v) => (int)$v)->all();
        }

        $totalServidores = 0;
        $totalLiquido = 0.0;

        foreach (array_chunk($funcionarioIds, 500) as $chunk) {
            // Buscar adiantamento 1ª parcela para deduzir na 2ª
            $adiantamentos = [];
            if ($tipo === 'DECIMO_TERCEIRO_2') {
                // FOLHA_COMPETENCIA / 100 = ano (ex: 202611 / 100 = 2026)
                $adiantamentos = DB::table('DETALHE_FOLHA as df')
                    ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
                    ->when(Schema::hasColumn('FOLHA', 'FOLHA_TIPO_EVENTO'),
                        fn($q) => $q->where('f.FOLHA_TIPO_EVENTO', 'DECIMO_TERCEIRO_1'),
                        fn($q) => $q->whereRaw('f.FOLHA_COMPETENCIA / 100 = ?', [$ano])
                    )
                    ->whereRaw('f.FOLHA_COMPETENCIA / 100 = ?', [$ano])
                    ->whereIn('df.FUNCIONARIO_ID', $chunk)
                    ->select('df.FUNCIONARIO_ID',
                        DB::raw('SUM(df.DETALHE_FOLHA_LIQUIDO) as total'))
                    ->groupBy('df.FUNCIONARIO_ID')
                    ->pluck('total', 'FUNCIONARIO_ID')
                    ->all();
            }

            $rows = [];
            foreach ($chunk as $funcId) {
                $calc = match ($tipo) {
                    'DECIMO_TERCEIRO_1' => $this->calcularPrimeiraParcela($funcId, $ano),
                    'DECIMO_TERCEIRO_2' => $this->calcularSegundaParcela($funcId, $ano, (float)($adiantamentos[$funcId] ?? 0)),
                    default => ['ok' => false],
                };

                if (!($calc['ok'] ?? false)) {
                    Log::warning('[DecimoTerceiro] Falha no cálculo', ['func' => $funcId, 'tipo' => $tipo, 'erro' => $calc['erro'] ?? '?']);
                    continue;
                }

                $rows[$funcId] = [
                    'FUNCIONARIO_ID' => $funcId,
                    'FOLHA_ID' => $folhaId,
                    'DETALHE_FOLHA_PROVENTOS' => $calc['valor_bruto'],
                    'DETALHE_FOLHA_DESCONTOS' => ($calc['valor_inss'] ?? 0) + ($calc['valor_irrf'] ?? 0),
                    'DETALHE_FOLHA_LIQUIDO' => $calc['valor_liquido'],
                    'DETALHE_BASE_PREV' => $calc['base_calculo'] ?? $calc['valor_bruto'],
                    'DETALHE_BASE_IRRF' => $calc['base_calculo'] ?? $calc['valor_bruto'],
                    'DETALHE_DESC_PREV' => $calc['valor_inss'] ?? 0,
                    'DETALHE_DESC_IRRF' => $calc['valor_irrf'] ?? 0,
                    'DETALHE_DESC_OUTROS' => $calc['adiantamento'] ?? 0,
                    'DETALHE_VINCULO_TIPO' => 'efetivo',
                    'DETALHE_COMPLEMENTO_SM' => 0,
                ];

                $totalServidores++;
                $totalLiquido += $calc['valor_liquido'];
            }

            if (!empty($rows)) {
                foreach (array_chunk(array_values($rows), 100) as $batch) {
                    $updateKeys = array_values(array_diff(array_keys($batch[0]), ['FUNCIONARIO_ID', 'FOLHA_ID']));
                    DB::table('DETALHE_FOLHA')->upsert($batch, ['FUNCIONARIO_ID', 'FOLHA_ID'], $updateKeys);
                }
            }
        }

        // Consolidar totais no cabeçalho da FOLHA
        $update = [];
        if (Schema::hasColumn('FOLHA', 'FOLHA_QTD_SERVIDORES')) {
            $update['FOLHA_QTD_SERVIDORES'] = $totalServidores;
        }
        if (Schema::hasColumn('FOLHA', 'FOLHA_VALOR_TOTAL')) {
            $update['FOLHA_VALOR_TOTAL'] = round($totalLiquido, 2);
        }
        if (!empty($update)) {
            DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->update($update);
        }

        Log::info('[DecimoTerceiro] Lote concluído', [
            'tipo' => $tipo, 'ano' => $ano, 'folha_id' => $folhaId,
            'servidores' => $totalServidores, 'total' => round($totalLiquido, 2),
        ]);

        return ['ok' => true, 'folha_id' => $folhaId, 'servidores' => $totalServidores, 'total_liquido' => round($totalLiquido, 2)];
    }

    // ── Helpers privados ───────────────────────────────────────────────────

    private function obterOuCriarFolha(string $tipo, int $ano, int $competencia): int
    {
        // FOLHA_COMPETENCIA / 100 = ano (INTEGER YYYYMM)
        $query = DB::table('FOLHA')->whereRaw('FOLHA_COMPETENCIA / 100 = ?', [$ano]);

        if (Schema::hasColumn('FOLHA', 'FOLHA_TIPO_EVENTO')) {
            $query->where('FOLHA_TIPO_EVENTO', $tipo);
        }

        $folha = $query->first();
        if ($folha) {
            return (int) $folha->FOLHA_ID;
        }

        $data = [
            'FOLHA_DESCRICAO' => match ($tipo) {
                'DECIMO_TERCEIRO_1' => "13º Salário {$ano} — 1ª Parcela",
                'DECIMO_TERCEIRO_2' => "13º Salário {$ano} — 2ª Parcela",
                default => "13º Rescisório {$ano}",
            },
            'FOLHA_COMPETENCIA' => $competencia,
            'FOLHA_TIPO' => 2, // tipo 2 = 13º no catálogo TABELA_GENERICA
        ];

        if (Schema::hasColumn('FOLHA', 'FOLHA_TIPO_EVENTO')) {
            $data['FOLHA_TIPO_EVENTO'] = $tipo;
        }

        return (int) DB::table('FOLHA')->insertGetId($data);
    }

    private function resolverVencimentoBase(int $funcionarioId): float
    {
        $row = DB::table('FUNCIONARIO as f')
            ->leftJoin('TABELA_SALARIAL as ts', function ($j) {
                $j->on('ts.CARREIRA_ID', '=', 'f.CARREIRA_ID')
                  ->on('ts.TABELA_CLASSE', '=', 'f.FUNCIONARIO_CLASSE')
                  ->on('ts.TABELA_REFERENCIA', '=', 'f.FUNCIONARIO_REFERENCIA');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcionarioId)
            ->selectRaw('
                ts.TABELA_VENCIMENTO_BASE,
                CASE WHEN c.CARGO_SALARIO IS NOT NULL THEN c.CARGO_SALARIO
                     WHEN c.CARGO_SALARIO_BASE IS NOT NULL THEN c.CARGO_SALARIO_BASE
                     ELSE NULL END AS CARGO_SAL
            ')
            ->first();

        $ts = (float) ($row->TABELA_VENCIMENTO_BASE ?? 0);
        return $ts > 0 ? $ts : (float) ($row->CARGO_SAL ?? 0);
    }

    private function resolverAdicionaisPermanentes(int $funcionarioId, float $vencBase): float
    {
        $rows = DB::table('ADICIONAL_SERVIDOR')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->where(fn($q) => $q->whereNull('ADICIONAL_VIGENCIA_FIM')->orWhere('ADICIONAL_VIGENCIA_FIM', '>=', now()->toDateString()))
            ->get();

        $total = 0.0;
        foreach ($rows as $ad) {
            $total += match ($ad->ADICIONAL_TIPO) {
                'fixo' => (float) $ad->ADICIONAL_VALOR,
                'percentual' => $vencBase * ((float) $ad->ADICIONAL_VALOR / 100),
                'percentual_sm' => self::SALARIO_MIN * ((float) $ad->ADICIONAL_VALOR / 100),
                default => 0.0,
            };
        }
        return $total;
    }

    private function resolverRegime(int $funcionarioId): string
    {
        $row = DB::table('FUNCIONARIO as f')
            ->leftJoin('VINCULO as v', 'v.VINCULO_ID', '=', 'f.VINCULO_ID')
            ->where('f.FUNCIONARIO_ID', $funcionarioId)
            ->select('v.VINCULO_REGIME', 'f.FUNCIONARIO_REGIME_PREV')
            ->first();
        return $row?->VINCULO_REGIME ?? $row?->FUNCIONARIO_REGIME_PREV ?? 'RPPS';
    }

    private function resolverDependentes(int $funcionarioId): int
    {
        return (int) DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('f.FUNCIONARIO_ID', $funcionarioId)
            ->value('p.PESSOA_DEPENDENTES_IRRF');
    }

    private function resolverAliquotaRpps(): float
    {
        try {
            $aliq = DB::table('RPPS_CONFIG')->orderByDesc('VIGENCIA_INICIO')->value('ALIQUOTA_SERVIDOR') ?? 14;
        } catch (\Throwable) {
            $aliq = 14;
        }
        return (float) $aliq / 100;
    }

    private function calcularMesesTrabalhados(string $dataRescisao, int $ano): int
    {
        $fim = \Carbon\Carbon::parse($dataRescisao);
        if ($fim->year < $ano) return 0;
        if ($fim->year > $ano) return 12;

        $meses = $fim->month;
        if ($fim->day < self::DIAS_MINIMOS_MES) {
            $meses--;
        }
        return max(0, $meses);
    }
}
