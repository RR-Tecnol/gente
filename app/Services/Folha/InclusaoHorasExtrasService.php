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
