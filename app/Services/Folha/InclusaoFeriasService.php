<?php

namespace App\Services\Folha;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-FER — 1/3 Constitucional de Férias e Abono Pecuniário
 *
 * Leis: CF/88 art. 7º XVII, Lei 8.112/90 art. 76-78 (estatutários),
 *       CLT art. 142-143 (celetistas), Súmula TST 328 (IR sobre pecúnia)
 *
 * Lógica:
 *   1. Para cada servidor com FERIAS com FERIAS_DATA_INICIO dentro da competencia atual:
 *      a. Calcular 1/3 constitucional = vencBase / 3
 *      b. Se FERIAS_DIAS_PECUNIA > 0: calcular abono pecuniário
 *         pecunia = (vencBase + 1/3) / 30 × dias_vendidos
 *      c. Injetar como LANCAMENTO_FOLHA tipo 'P' (idempotente via FERIAS_FOLHA_APLICADA)
 *
 *   Incidências:
 *   - 1/3 constitucional: incide INSS e IRRF (Súmula TST 60)
 *   - Abono pecuniário:   incide INSS e IRRF (Súmula TST 328)
 *
 * Idempotência: FERIAS_FOLHA_APLICADA = competencia impede duplicação em reprocessamento.
 */
class InclusaoFeriasService
{
    // Código da rubrica do 1/3 constitucional no catálogo EVENTO
    private const RUBRICA_UM_TERCO = '500';
    // Código da rubrica de abono pecuniário
    private const RUBRICA_PECUNIA = '501';

    public function incluirParaFolha(int $folhaId, array $funcionarioIds, string $competencia): void
    {
        if (empty($funcionarioIds)) {
            return;
        }

        // Calcular mês/ano da competência (YYYYMM → date range)
        $ano  = (int) substr($competencia, 0, 4);
        $mes  = (int) substr($competencia, 4, 2);
        $inicioMes = sprintf('%04d-%02d-01', $ano, $mes);
        $fimMes    = date('Y-m-t', strtotime($inicioMes)); // último dia do mês

        // Verificar se coluna FERIAS_FOLHA_APLICADA existe (schema-defensive)
        $temControleIdempotencia = Schema::hasColumn('FERIAS', 'FERIAS_FOLHA_APLICADA');
        $temPecunia = Schema::hasColumn('FERIAS', 'FERIAS_DIAS_PECUNIA');

        // Buscar férias dos servidores que começam nesta competência
        $query = DB::table('FERIAS as frs')
            ->whereIn('frs.FUNCIONARIO_ID', $funcionarioIds)
            ->where('frs.FERIAS_DATA_INICIO', '>=', $inicioMes)
            ->where('frs.FERIAS_DATA_INICIO', '<=', $fimMes);

        // Idempotência: não reprocessar férias já incluídas nesta competência
        if ($temControleIdempotencia) {
            $query->where(function ($q) use ($competencia) {
                $q->whereNull('FERIAS_FOLHA_APLICADA')
                  ->orWhere('FERIAS_FOLHA_APLICADA', '!=', $competencia);
            });
        }

        $feriasPendentes = $query->get();

        if ($feriasPendentes->isEmpty()) {
            Log::info('[InclusaoFerias] Nenhuma férias pendente para a competência.', [
                'folha_id' => $folhaId,
                'competencia' => $competencia,
            ]);
            return;
        }

        $lancamentos = [];
        $feriasMarcadas = [];

        foreach ($feriasPendentes as $ferias) {
            $funcId = (int) $ferias->FUNCIONARIO_ID;

            // Resolver vencimento base do servidor
            $vencBase = $this->resolverVencimentoBase($funcId);
            if ($vencBase <= 0) {
                Log::warning('[InclusaoFerias] Vencimento base zero — ignorando.', [
                    'funcionario_id' => $funcId,
                    'ferias_id' => $ferias->FERIAS_ID,
                ]);
                continue;
            }

            // 1. Lançamento do 1/3 constitucional
            $umTerco = round($vencBase / 3, 2);
            $lancamentos[] = [
                'FOLHA_ID' => $folhaId,
                'FUNCIONARIO_ID' => $funcId,
                'LANCAMENTO_TIPO' => 'P',
                'LANCAMENTO_CODIGO' => self::RUBRICA_UM_TERCO,
                'LANCAMENTO_DESCRICAO' => '1/3 Constitucional de Férias',
                'LANCAMENTO_VALOR_UNITARIO' => $umTerco,
                'LANCAMENTO_REFERENCIA' => 1,
                'LANCAMENTO_VALOR_TOTAL' => $umTerco,
                'LANCAMENTO_INCIDE_PREV' => 1,
                'LANCAMENTO_INCIDE_IRRF' => 1,
                'LANCAMENTO_STATUS' => 'INCLUIDA_FOLHA',
                'LANCAMENTO_ORIGEM' => 'FERIAS_ID:' . $ferias->FERIAS_ID,
            ];

            // 2. Abono Pecuniário (se houver dias vendidos)
            if ($temPecunia) {
                $diasPecunia = (int) ($ferias->FERIAS_DIAS_PECUNIA ?? 0);

                if ($diasPecunia > 0) {
                    // Validação legal: máximo 10 dias (1/3 de 30)
                    $diasPecunia = min($diasPecunia, 10);
                    // Fórmula: (vencBase + 1/3) / 30 × dias_vendidos
                    $baseDiaria = ($vencBase + $umTerco) / 30;
                    $valorPecunia = round($baseDiaria * $diasPecunia, 2);

                    $lancamentos[] = [
                        'FOLHA_ID' => $folhaId,
                        'FUNCIONARIO_ID' => $funcId,
                        'LANCAMENTO_TIPO' => 'P',
                        'LANCAMENTO_CODIGO' => self::RUBRICA_PECUNIA,
                        'LANCAMENTO_DESCRICAO' => "Abono Pecuniário de Férias ({$diasPecunia} dias)",
                        'LANCAMENTO_VALOR_UNITARIO' => round($baseDiaria, 4),
                        'LANCAMENTO_REFERENCIA' => $diasPecunia,
                        'LANCAMENTO_VALOR_TOTAL' => $valorPecunia,
                        'LANCAMENTO_INCIDE_PREV' => 1,
                        'LANCAMENTO_INCIDE_IRRF' => 1,
                        'LANCAMENTO_STATUS' => 'INCLUIDA_FOLHA',
                        'LANCAMENTO_ORIGEM' => 'FERIAS_ID:' . $ferias->FERIAS_ID . ':PECUNIA',
                    ];
                }
            }

            // Marcar para atualizar FERIAS_FOLHA_APLICADA (idempotência)
            $feriasMarcadas[] = (int) $ferias->FERIAS_ID;
        }

        // Persistir lançamentos em lotes de 100
        if (!empty($lancamentos)) {
            foreach (array_chunk($lancamentos, 100) as $batch) {
                DB::table('LANCAMENTO_FOLHA')->insert($batch);
            }

            Log::info('[InclusaoFerias] Lançamentos inseridos.', [
                'folha_id' => $folhaId,
                'competencia' => $competencia,
                'lancamentos' => count($lancamentos),
                'servidores' => count($feriasMarcadas),
            ]);
        }

        // Marcar férias como aplicadas nesta competência (idempotência)
        if ($temControleIdempotencia && !empty($feriasMarcadas)) {
            DB::table('FERIAS')
                ->whereIn('FERIAS_ID', $feriasMarcadas)
                ->update(['FERIAS_FOLHA_APLICADA' => $competencia]);
        }
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
}
