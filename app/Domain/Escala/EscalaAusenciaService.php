<?php

namespace App\Domain\Escala;

use App\MyLibs\RTG;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EscalaAusenciaService
{
    // R72: path debug removido — log em arquivo só se DEBUG_LOG_PATH existir como diretório (no-op em produção)
    private const DEBUG_LOG_PATH = null;

    public static function bloqueadaPorAfastamento(int $funcionarioId, string $dataIso): bool
    {
        if ($funcionarioId <= 0 || ! Schema::hasTable('AFASTAMENTO')) {
            return false;
        }

        $data = Carbon::parse($dataIso)->toDateString();
        $competencia = Carbon::parse($dataIso)->format('Y-m');
        $idx = self::indexarPorFuncionarioDia([$funcionarioId], $competencia);
        $dia = (int) Carbon::parse($dataIso)->format('d');

        return isset($idx[$funcionarioId][$dia]);
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function indexarPorFuncionarioDia(array $funcionarioIds, string $competenciaYm): array
    {
        // #region agent log
        self::debugLog('h1', 'EscalaAusenciaService.php:33', 'Entrada indexarPorFuncionarioDia', [
            'funcionarios_count' => count($funcionarioIds),
            'competencia' => $competenciaYm,
            'has_afastamento_table' => Schema::hasTable('AFASTAMENTO'),
        ]);
        // #endregion
        if ($funcionarioIds === [] || ! Schema::hasTable('AFASTAMENTO')) {
            return [];
        }

        [$inicioComp, $fimComp] = self::periodoCompetencia($competenciaYm);
        $tipos = self::tiposAfastamentoPorId();
        $inicioCol = self::colunaInicioAfastamento();
        $fimCol = self::colunaFimAfastamento();
        $tipoCol = self::colunaTipoAfastamento();

        $select = [
            'AFASTAMENTO_ID',
            'FUNCIONARIO_ID',
            $inicioCol.' as DATA_INICIO',
            $fimCol.' as DATA_FIM',
            $tipoCol.' as TIPO_RAW',
        ];

        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_STATUS')) {
            $select[] = 'AFASTAMENTO_STATUS';
        }

        $rows = DB::table('AFASTAMENTO')
            ->whereIn('FUNCIONARIO_ID', $funcionarioIds)
            ->whereDate($inicioCol, '<=', $fimComp->toDateString())
            ->whereDate($fimCol, '>=', $inicioComp->toDateString())
            ->when(
                Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_STATUS'),
                fn ($q) => $q->whereNotIn(DB::raw('UPPER(COALESCE(AFASTAMENTO_STATUS, \'\'))'), ['CANCELADO', 'INDEFERIDO', 'REPROVADO'])
            )
            ->orderBy('FUNCIONARIO_ID')
            ->orderBy($inicioCol)
            ->get($select);
        // #region agent log
        self::debugLog('h1', 'EscalaAusenciaService.php:74', 'Linhas de afastamento retornadas', [
            'rows_count' => $rows->count(),
            'inicio_col' => $inicioCol,
            'fim_col' => $fimCol,
            'tipo_col' => $tipoCol,
        ]);
        // #endregion

        $index = [];
        $tipoNomeVazioLogado = false;
        foreach ($rows as $r) {
            $funcionarioId = (int) ($r->FUNCIONARIO_ID ?? 0);
            if ($funcionarioId <= 0) {
                continue;
            }

            $inicioAfast = Carbon::parse((string) $r->DATA_INICIO)->startOfDay();
            $fimAfast = Carbon::parse((string) $r->DATA_FIM)->endOfDay();
            $inicioEfetivo = $inicioAfast->copy()->max($inicioComp);
            $fimEfetivo = $fimAfast->copy()->min($fimComp);
            if ($inicioEfetivo->gt($fimEfetivo)) {
                continue;
            }

            $tipoRaw = $r->TIPO_RAW ?? null;
            $tipoNome = self::resolverTipoNome($tipoRaw, $tipos);
            if (! $tipoNomeVazioLogado && trim($tipoNome) === '') {
                // #region agent log
                self::debugLog('h2', 'EscalaAusenciaService.php:97', 'Tipo de afastamento vazio após resolução', [
                    'afastamento_id' => (int) ($r->AFASTAMENTO_ID ?? 0),
                    'tipo_raw' => $tipoRaw,
                    'tipos_map_count' => count($tipos),
                ]);
                // #endregion
                $tipoNomeVazioLogado = true;
            }
            $sigla = self::mapearSiglaNormativa($tipoRaw, $tipoNome);
            $cor = self::mapearCorSigla($sigla);
            $ultrapassa15 = self::ultrapassa15Dias($sigla, $inicioAfast, $fimAfast);

            for ($dia = $inicioEfetivo->copy(); $dia->lte($fimEfetivo); $dia->addDay()) {
                $diaMes = (int) $dia->format('d');
                $payload = [
                    'sigla' => $sigla,
                    'tipo' => $tipoNome,
                    'cor' => $cor,
                    'inicio' => $inicioAfast->toDateString(),
                    'fim' => $fimAfast->toDateString(),
                    'servidor_afastamento_id' => (int) ($r->AFASTAMENTO_ID ?? 0),
                    'ultrapassa_15_dias' => $ultrapassa15,
                    'bloqueada_por_afastamento' => true,
                    // Camada alias semântica (mantém física atual AFASTAMENTO/TABELA_GENERICA)
                    'servidorAfastamento' => [
                        'id' => (int) ($r->AFASTAMENTO_ID ?? 0),
                        'funcionario_id' => $funcionarioId,
                        'data_inicio' => $inicioAfast->toDateString(),
                        'data_fim' => $fimAfast->toDateString(),
                    ],
                    'rhTipoAfastamento' => [
                        'id' => is_numeric($tipoRaw) ? (int) $tipoRaw : null,
                        'descricao' => $tipoNome,
                        'sigla' => $sigla,
                    ],
                ];

                $index[$funcionarioId][$diaMes] = $payload;
            }
        }

        return $index;
    }

    private static function colunaInicioAfastamento(): string
    {
        return Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_DATA_INICIO')
            ? 'AFASTAMENTO_DATA_INICIO'
            : 'created_at';
    }

    private static function colunaFimAfastamento(): string
    {
        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_DATA_FIM')) {
            return 'AFASTAMENTO_DATA_FIM';
        }

        return self::colunaInicioAfastamento();
    }

    private static function colunaTipoAfastamento(): string
    {
        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_TIPO')) {
            return 'AFASTAMENTO_TIPO';
        }
        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_TIPO_NOME')) {
            return 'AFASTAMENTO_TIPO_NOME';
        }

        return 'AFASTAMENTO_ID';
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function periodoCompetencia(string $competenciaYm): array
    {
        $ref = Carbon::createFromFormat('Y-m', $competenciaYm);

        return [$ref->copy()->startOfMonth(), $ref->copy()->endOfMonth()];
    }

    /**
     * @return array<int, string>
     */
    private static function tiposAfastamentoPorId(): array
    {
        if (! Schema::hasTable('TABELA_GENERICA')) {
            return [];
        }
        $descCol = Schema::hasColumn('TABELA_GENERICA', 'COLUNA_DESCRICAO') ? 'COLUNA_DESCRICAO' : 'DESCRICAO';

        $tipos = DB::table('TABELA_GENERICA')
            ->where('TABELA_ID', RTG::TIPO_AFASTAMENTO)
            ->where('COLUNA_ID', '!=', 0)
            ->pluck($descCol, 'COLUNA_ID')
            ->map(fn ($v) => (string) $v)
            ->all();
        // #region agent log
        self::debugLog('h2', 'EscalaAusenciaService.php:193', 'Mapa de tipos carregado', [
            'desc_col' => $descCol,
            'tipos_count' => count($tipos),
        ]);
        // #endregion

        return $tipos;
    }

    /**
     * @param  array<int, string>  $tipos
     */
    private static function resolverTipoNome($tipoRaw, array $tipos): string
    {
        if (is_numeric($tipoRaw)) {
            return (string) ($tipos[(int) $tipoRaw] ?? ('Tipo '.$tipoRaw));
        }

        $txt = trim((string) $tipoRaw);

        return $txt !== '' ? $txt : 'Afastamento';
    }

    private static function ultrapassa15Dias(string $sigla, Carbon $inicio, Carbon $fim): bool
    {
        if (! in_array($sigla, ['LM', 'LMA'], true)) {
            return false;
        }

        return $inicio->diffInDays($fim) + 1 > 15;
    }

    private static function normalizarTexto(string $txt): string
    {
        $base = mb_strtolower($txt, 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);

        return $ascii !== false ? $ascii : $base;
    }

    private static function mapearSiglaNormativa($tipoRaw, string $tipoNome): string
    {
        if (is_numeric($tipoRaw)) {
            $mapId = [
                4 => 'LM',
                5 => 'LP',
                8 => 'LM',
                9 => 'LMA',
                12 => 'AE',
            ];
            $id = (int) $tipoRaw;
            if (isset($mapId[$id])) {
                return $mapId[$id];
            }
        }

        $nome = self::normalizarTexto($tipoNome);
        if (str_contains($nome, 'medic') || str_contains($nome, 'atestado') || str_contains($nome, 'doenca')) {
            return 'LM';
        }
        if (str_contains($nome, 'ferias')) {
            return 'FR';
        }
        if (str_contains($nome, 'premio')) {
            return 'LP';
        }
        if (str_contains($nome, 'matern') || str_contains($nome, 'adocao') || str_contains($nome, 'patern')) {
            return 'LMA';
        }
        if (str_contains($nome, 'particular') || str_contains($nome, 'sem vencimento')) {
            return 'LPA';
        }
        if (str_contains($nome, 'capac') || str_contains($nome, 'estudo') || str_contains($nome, 'militar') || str_contains($nome, 'mandato')) {
            return 'AE';
        }
        if (str_contains($nome, 'falta') && str_contains($nome, 'nao')) {
            return 'FNJ';
        }

        return 'AE';
    }

    private static function mapearCorSigla(string $sigla): string
    {
        return match ($sigla) {
            'LM' => '#E74C3C',
            'FR' => '#3498DB',
            'LP' => '#9B59B6',
            'AE' => '#1ABC9C',
            'LMA' => '#E84393',
            'LPA' => '#8E44AD',
            'FNJ' => '#2C3E50',
            default => '#64748B',
        };
    }

    private static function debugLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        // R72: no-op em produção (DEBUG_LOG_PATH = null)
        if (self::DEBUG_LOG_PATH === null) {
            return;
        }

        try {
            $payload = [
                'sessionId' => 'f94096',
                'runId' => 'runtime-http-validation',
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            @file_put_contents(self::DEBUG_LOG_PATH, json_encode($payload, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            // no-op em modo debug
        }
    }
}

