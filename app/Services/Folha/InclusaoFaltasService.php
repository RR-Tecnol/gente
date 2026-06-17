<?php

namespace App\Services\Folha;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * P0-1 (Alternativa A) — Conversão de faltas apuradas em LANCAMENTO_FOLHA de desconto.
 *
 * Lê APURACAO_PONTO da competência ANTERIOR à folha (regra: faltas de Janeiro
 * descontam Fevereiro), filtrada por APURACAO_STATUS='FECHADA' e ainda não aplicada.
 *
 * Para cada funcionário com APURACAO_HORAS_FALTA > 0, calcula:
 *   diasFalta    = horasFalta / 8 (jornada padrão)
 *   valorDesconto = (vencimentoBase / diasMes) * diasFalta
 * E gera LANCAMENTO_FOLHA tipo 'D' com RUBRICA_CODIGO='906' (Desconto Faltas).
 *
 * Idempotência:
 *   - Filtro pega apenas APURACAO_STATUS='FECHADA' (não pega 'ABERTA' ou 'APLICADA_FOLHA')
 *   - Após inserir, marca APURACAO_STATUS='APLICADA_FOLHA' + APURACAO_FOLHA_ID
 *   - Re-execução pra mesma (folha, funcionário) não duplica
 *
 * Padrão arquitetural: análogo a {@see InclusaoHorasExtrasService}.
 */
final class InclusaoFaltasService
{
    /** Código da rubrica "Desconto Faltas" (catálogo PMSL — RubricasCatalogoSeeder). */
    private const RUBRICA_DESCONTO_FALTA_CODIGO = '906';

    /** Jornada padrão usada pra converter horas em dias. Configurável no futuro. */
    private const JORNADA_HORAS_DIA = 8.0;

    /**
     * Inclui descontos de falta como LANCAMENTO_FOLHA para um lote de funcionários.
     *
     * @param  int           $folhaId
     * @param  list<int>     $funcionarioIds
     * @param  string        $competencia  YYYY-MM ou YYYYMM da FOLHA atual (a aplicar)
     * @return array{faltas_incluidas: int}
     */
    public function incluirParaFolha(int $folhaId, array $funcionarioIds, string $competencia): array
    {
        $compFormatada = $this->normalizarCompetencia($competencia);
        $compAnterior  = $this->competenciaAnterior($compFormatada);

        $funcIds = array_values(array_unique(array_map('intval', $funcionarioIds)));
        if ($funcIds === []) {
            return ['faltas_incluidas' => 0];
        }

        // Pré-condições de schema
        if (! Schema::hasTable('APURACAO_PONTO') || ! Schema::hasTable('LANCAMENTO_FOLHA')) {
            Log::warning('[InclusaoFaltas] Tabelas APURACAO_PONTO/LANCAMENTO_FOLHA ausentes — operação ignorada.');
            return ['faltas_incluidas' => 0];
        }

        $rubricaId = $this->resolverRubricaIdPorCodigo(self::RUBRICA_DESCONTO_FALTA_CODIGO);
        if ($rubricaId === null) {
            Log::warning('[InclusaoFaltas] Rubrica RUBRICA_CODIGO=906 (Desconto Faltas) não encontrada — operação ignorada.', [
                'sugestao' => 'Rodar seeder RubricasCatalogoSeeder.',
            ]);
            return ['faltas_incluidas' => 0];
        }

        return DB::transaction(function () use ($folhaId, $funcIds, $compAnterior, $rubricaId) {
            // 1) Buscar APURACAO_PONTO fechadas com horas_falta > 0
            $apuracoes = DB::table('APURACAO_PONTO')
                ->whereIn('FUNCIONARIO_ID', $funcIds)
                ->where('APURACAO_COMPETENCIA', $compAnterior)
                ->where('APURACAO_STATUS', 'FECHADA')
                ->where('APURACAO_HORAS_FALTA', '>', 0)
                ->get();

            if ($apuracoes->isEmpty()) {
                return ['faltas_incluidas' => 0];
            }

            // 2) Resolver vencimento base por funcionário (mesma estratégia do MotorFolhaService)
            $apuracoesIds   = $apuracoes->pluck('APURACAO_ID')->all();
            $apuracoesFunc  = $apuracoes->pluck('FUNCIONARIO_ID')->map(fn ($v) => (int) $v)->all();
            $vencBaseMap    = $this->resolverVencimentoBaseLote($apuracoesFunc);

            // 3) Calcular dias do mês de competência ANTERIOR (não da folha atual!)
            $diasMesAnterior = $this->diasNoMesAnterior($compAnterior);

            // 4) Para cada apuração, criar LANCAMENTO_FOLHA tipo D
            $count = 0;
            foreach ($apuracoes as $ap) {
                $fid          = (int) $ap->FUNCIONARIO_ID;
                $horasFalta   = (float) $ap->APURACAO_HORAS_FALTA;
                $vencBase     = (float) ($vencBaseMap[$fid] ?? 0);

                if ($horasFalta <= 0 || $vencBase <= 0) {
                    continue;
                }

                $diasFalta     = $horasFalta / self::JORNADA_HORAS_DIA;
                $valorDesconto = round(($vencBase / $diasMesAnterior) * $diasFalta, 2);

                if ($valorDesconto <= 0) {
                    continue;
                }

                $lancamento = [
                    'FUNCIONARIO_ID'           => $fid,
                    'FOLHA_ID'                 => $folhaId,
                    'RUBRICA_ID'               => $rubricaId,
                    'LANCAMENTO_TIPO'          => 'D',
                    'LANCAMENTO_QTDE'          => round($diasFalta, 2),
                    'LANCAMENTO_VALOR_UNIT'    => round($vencBase / $diasMesAnterior, 2),
                    'LANCAMENTO_VALOR_TOTAL'   => $valorDesconto,
                    'LANCAMENTO_INCIDE_PREV'   => false,
                    'LANCAMENTO_INCIDE_IRRF'   => false,
                    'LANCAMENTO_ORIGEM'        => 'ponto',
                    'LANCAMENTO_OBS'           => sprintf(
                        'P0-1 InclusaoFaltas: APURACAO_ID=%d competencia=%s horas=%.2f dias=%.2f',
                        (int) $ap->APURACAO_ID,
                        $compAnterior,
                        $horasFalta,
                        $diasFalta
                    ),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DB::table('LANCAMENTO_FOLHA')->insert($lancamento);
                $count++;
            }

            // 5) Marcar apurações como aplicadas (idempotência) — APENAS se a coluna existir
            $update = ['APURACAO_STATUS' => 'APLICADA_FOLHA'];
            if (Schema::hasColumn('APURACAO_PONTO', 'APURACAO_FOLHA_ID')) {
                $update['APURACAO_FOLHA_ID'] = $folhaId;
            }

            DB::table('APURACAO_PONTO')
                ->whereIn('APURACAO_ID', $apuracoesIds)
                ->update($update);

            Log::info('[InclusaoFaltas] Descontos de falta gerados', [
                'folha_id'          => $folhaId,
                'competencia_folha' => $compAnterior,
                'apuracoes'         => count($apuracoesIds),
                'lancamentos'       => $count,
            ]);

            return ['faltas_incluidas' => $count];
        });
    }

    /**
     * Aceita "202604" ou "2026-04" — retorna sempre "2026-04".
     */
    private function normalizarCompetencia(string $competencia): string
    {
        $c = preg_replace('/\D/', '', $competencia);
        if (strlen($c) >= 6) {
            return substr($c, 0, 4) . '-' . substr($c, 4, 2);
        }
        return $competencia;
    }

    /**
     * Dado "2026-02", retorna "2026-01" (compat YYYY-MM).
     * Dado "2026-01", retorna "2025-12".
     */
    private function competenciaAnterior(string $compYm): string
    {
        return Carbon::parse($compYm . '-01')->subMonthNoOverflow()->format('Y-m');
    }

    /**
     * Dias reais do mês de uma competência YYYY-MM (28/29/30/31).
     */
    private function diasNoMesAnterior(string $compYm): int
    {
        return Carbon::parse($compYm . '-01')->daysInMonth;
    }

    /**
     * Resolve RUBRICA_ID por RUBRICA_CODIGO. Cache em memória.
     * Mesmo padrão do InclusaoHorasExtrasService.
     */
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

    /**
     * Para cada funcionário, resolve o vencimento base proporcional ao salário do contrato.
     * Reutiliza a mesma estratégia do MotorFolhaService::calcularLoteParaFuncionarios:
     *   1. JOIN TABELA_SALARIAL via (CARREIRA_ID, FUNCIONARIO_CLASSE, FUNCIONARIO_REFERENCIA)
     *   2. Fallback: CARGO.CARGO_SALARIO ou CARGO.CARGO_SALARIO_BASE
     *
     * Retorna mapa [funcionario_id => vencimento_base_decimal].
     *
     * @param list<int> $funcionarioIds
     * @return array<int, float>
     */
    private function resolverVencimentoBaseLote(array $funcionarioIds): array
    {
        if ($funcionarioIds === []) {
            return [];
        }

        $rows = DB::table('FUNCIONARIO as f')
            ->leftJoin('TABELA_SALARIAL as ts', function ($j) {
                $j->on('ts.CARREIRA_ID', '=', 'f.CARREIRA_ID')
                    ->on('ts.TABELA_CLASSE', '=', 'f.FUNCIONARIO_CLASSE')
                    ->on('ts.TABELA_REFERENCIA', '=', 'f.FUNCIONARIO_REFERENCIA');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->whereIn('f.FUNCIONARIO_ID', $funcionarioIds)
            ->select([
                'f.FUNCIONARIO_ID',
                'ts.TABELA_VENCIMENTO_BASE',
                Schema::hasColumn('CARGO', 'CARGO_SALARIO')
                    ? 'c.CARGO_SALARIO'
                    : (Schema::hasColumn('CARGO', 'CARGO_SALARIO_BASE') ? 'c.CARGO_SALARIO_BASE as CARGO_SALARIO' : DB::raw('NULL as CARGO_SALARIO')),
            ])
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $venc = (float) ($r->TABELA_VENCIMENTO_BASE ?? 0);
            if ($venc <= 0) {
                $venc = (float) ($r->CARGO_SALARIO ?? 0);
            }
            $map[(int) $r->FUNCIONARIO_ID] = $venc;
        }

        return $map;
    }
}
