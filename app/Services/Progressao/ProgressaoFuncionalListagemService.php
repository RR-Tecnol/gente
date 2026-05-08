<?php

namespace App\Services\Progressao;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgressaoFuncionalListagemService
{
    private const CHUNK = 400;

    private const PER_PAGE_DEFAULT = 50;

    private const PER_PAGE_MAX = 200;

    public function __construct(
        private ProgressaoFuncionalElegibilidadeService $eleg
    ) {}

    public function normalizePagination(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage ?: self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX);

        return [$page, $perPage];
    }

    public function meta(int $page, int $perPage, int $total): array
    {
        $last = max(1, (int) ceil($total / $perPage));

        return [
            'current_page' => $page,
            'last_page' => $last,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * @return array{servidores: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function paginateTodos(int $page, int $perPage, ?string $busca, ?int $setorId): array
    {
        [$page, $perPage] = $this->normalizePagination($page, $perPage);
        $q = $this->servidoresBaseQuery($busca, $setorId, includeSetorScalar: true);
        $total = (clone $q)->count('f.FUNCIONARIO_ID');
        $rows = (clone $q)
            ->orderBy('f.FUNCIONARIO_ID')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $funcIds = $rows->pluck('FUNCIONARIO_ID');
        $avaliacoes = $this->batchAvaliacoes($funcIds);
        $comPenalidade = $this->batchPenalidade($funcIds);

        $servidores = $rows->map(function ($func) use ($avaliacoes, $comPenalidade) {
            $func->_avaliacao = $avaliacoes->get($func->FUNCIONARIO_ID);
            $func->_com_penalidade = isset($comPenalidade[$func->FUNCIONARIO_ID]);

            $cfg = $this->eleg->getProgConfig($func->CARREIRA_ID);
            $eleg = $this->eleg->avaliarEleg($func, $cfg);
            $venc = $this->eleg->getVencBase($func);
            $anos = $func->FUNCIONARIO_DATA_INICIO ? (int) Carbon::now()->diffInYears(Carbon::parse($func->FUNCIONARIO_DATA_INICIO)) : 0;
            $anu = $venc * (($cfg->CONFIG_ANUENIO_PCT / 100) * $anos);

            return [
                'id' => $func->FUNCIONARIO_ID,
                'nome' => $func->PESSOA_NOME,
                'cargo' => $func->CARGO_NOME ?? '—',
                'carreira' => $func->CARREIRA_NOME ?? null,
                'classe' => $func->FUNCIONARIO_CLASSE ?? '—',
                'referencia' => $func->FUNCIONARIO_REFERENCIA ?? '—',
                'salario_atual' => round($venc + $anu, 2),
                'novo_vencimento' => $eleg['novo_vencimento'],
                'proxima_ref' => $eleg['proxima_referencia'],
                'aumento' => $eleg['novo_vencimento'] ? round($eleg['novo_vencimento'] - $venc, 2) : 0,
                'elegivel' => $eleg['elegivel'],
                'elegivel_promocao' => $eleg['elegivel_promocao'],
                'bloqueios' => $eleg['bloqueios'],
                'meses_na_ref' => $eleg['meses_na_referencia'],
                'nota' => $eleg['nota_obtida'],
                'ultima_progressao' => $func->FUNCIONARIO_DATA_ULTIMA_PROGRESSAO,
                'setor_id' => $func->setor_id !== null ? (int) $func->setor_id : null,
            ];
        })->values()->all();

        return [
            'servidores' => $servidores,
            'meta' => $this->meta($page, $perPage, $total),
        ];
    }

    /**
     * @return array{mes: string, total: int, elegiveis: list<array<string, mixed>>, gerado_em: string, meta: array<string, int>}
     */
    public function paginateElegiveis(int $page, int $perPage, ?string $busca, ?int $setorId): array
    {
        [$page, $perPage] = $this->normalizePagination($page, $perPage);
        $afterSkip = ($page - 1) * $perPage;
        $key = $this->elegiveisTotalCacheKey($setorId, $busca, $perPage);
        $cachedTotal = Cache::get($key);
        $needFullCount = $cachedTotal === null;
        $ttl = $this->elegiveisTotalCacheTtlSeconds();

        if ($cachedTotal !== null && $afterSkip >= (int) $cachedTotal) {
            $t = (int) $cachedTotal;

            return [
                'mes' => now()->format('m/Y'),
                'total' => $t,
                'elegiveis' => [],
                'gerado_em' => now()->toDateTimeString(),
                'meta' => $this->meta($page, $perPage, $t),
            ];
        }

        $items = [];
        $lastId = 0;
        $elegSeen = 0;

        while (true) {
            $q = $this->servidoresBaseQuery($busca, $setorId, includeSetorScalar: false)
                ->where('f.FUNCIONARIO_ID', '>', $lastId)
                ->orderBy('f.FUNCIONARIO_ID')
                ->limit(self::CHUNK);
            $chunk = $q->get();
            if ($chunk->isEmpty()) {
                break;
            }
            $ids = $chunk->pluck('FUNCIONARIO_ID');
            $avaliacoes = $this->batchAvaliacoes($ids);
            $comPenalidade = $this->batchPenalidade($ids);
            $stopChunk = false;

            foreach ($chunk as $func) {
                $lastId = (int) $func->FUNCIONARIO_ID;
                $func->_avaliacao = $avaliacoes->get($func->FUNCIONARIO_ID);
                $func->_com_penalidade = isset($comPenalidade[$func->FUNCIONARIO_ID]);
                $cfg = $this->eleg->getProgConfig($func->CARREIRA_ID);
                $eleg = $this->eleg->avaliarEleg($func, $cfg);
                if (! $eleg['elegivel']) {
                    continue;
                }
                if ($elegSeen >= $afterSkip && count($items) < $perPage) {
                    $venc = $this->eleg->getVencBase($func);
                    $anos = $func->FUNCIONARIO_DATA_INICIO ? (int) Carbon::now()->diffInYears(Carbon::parse($func->FUNCIONARIO_DATA_INICIO)) : 0;
                    $anu = $venc * (($cfg->CONFIG_ANUENIO_PCT / 100) * $anos);
                    $items[] = [
                        'id' => $func->FUNCIONARIO_ID,
                        'nome' => $func->PESSOA_NOME,
                        'cargo' => $func->CARGO_NOME ?? '—',
                        'carreira' => $func->CARREIRA_NOME ?? '—',
                        'classe' => $func->FUNCIONARIO_CLASSE ?? '—',
                        'referencia' => $func->FUNCIONARIO_REFERENCIA ?? '—',
                        'ref_atual' => $func->FUNCIONARIO_REFERENCIA ?? '—',
                        'proxima_ref' => $eleg['proxima_referencia'],
                        'salario_atual' => round($venc + $anu, 2),
                        'novo_vencimento' => $eleg['novo_vencimento'],
                        'aumento' => round(($eleg['novo_vencimento'] ?? $venc) - $venc, 2),
                        'meses_na_ref' => $eleg['meses_na_referencia'],
                        'nota' => $eleg['nota_obtida'],
                    ];
                }
                $elegSeen++;
                if (! $needFullCount) {
                    if (count($items) >= $perPage) {
                        $stopChunk = true;
                        break;
                    }
                    if ($cachedTotal !== null && $elegSeen >= (int) $cachedTotal) {
                        $stopChunk = true;
                        break;
                    }
                }
            }

            if ($stopChunk) {
                break;
            }
        }

        $elegTotal = $needFullCount ? $elegSeen : (int) $cachedTotal;
        if ($needFullCount) {
            Cache::put($key, $elegSeen, $ttl);
        }

        return [
            'mes' => now()->format('m/Y'),
            'total' => $elegTotal,
            'elegiveis' => $items,
            'gerado_em' => now()->toDateTimeString(),
            'meta' => $this->meta($page, $perPage, $elegTotal),
        ];
    }

    /**
     * Invalida totais cacheados da listagem de elegíveis (versão global da chave).
     */
    public static function invalidateElegiveisTotalCache(): void
    {
        $v = (int) Cache::get('pf_eleg_cache_ver', 1);
        Cache::forever('pf_eleg_cache_ver', $v + 1);
    }

    private function elegiveisTotalCacheKey(?int $setorId, ?string $busca, int $perPage): string
    {
        $ver = (int) Cache::get('pf_eleg_cache_ver', 1);
        $norm = $this->normalizeBuscaForCache($busca);
        $h = sha1(json_encode([$setorId, $norm, $perPage]));

        return 'pf_eleg_total:v'.$ver.':'.$h;
    }

    private function normalizeBuscaForCache(?string $busca): string
    {
        $t = trim((string) ($busca ?? ''));
        if ($t === '') {
            return '';
        }

        return (string) preg_replace('/\s+/u', ' ', $t);
    }

    private function elegiveisTotalCacheTtlSeconds(): int
    {
        $raw = (int) env('GENTE_PF_ELEGIVEIS_TOTAL_TTL', 180);

        return max(120, min(600, $raw > 0 ? $raw : 180));
    }

    /**
     * @return array<string, mixed>
     */
    public function impactoAgregado(): array
    {
        $rec = DB::table('RECEITA_MUNICIPIO')->orderByDesc('RECEITA_ANO')->first();
        $rcl = (float) ($rec->RECEITA_CORRENTE_LIQUIDA ?? 50000000);
        $folhaMes = (float) ($rec->RECEITA_FOLHA_ATUAL ?? 2000000);

        $impTotal = 0.0;
        $impactados = 0;
        $lastId = 0;

        while (true) {
            $chunk = $this->servidoresBaseQuery(null, null, false)
                ->where('f.FUNCIONARIO_ID', '>', $lastId)
                ->orderBy('f.FUNCIONARIO_ID')
                ->limit(self::CHUNK)
                ->get();
            if ($chunk->isEmpty()) {
                break;
            }
            $ids = $chunk->pluck('FUNCIONARIO_ID');
            $avaliacoes = $this->batchAvaliacoes($ids);
            $comPenalidade = $this->batchPenalidade($ids);

            foreach ($chunk as $func) {
                $lastId = (int) $func->FUNCIONARIO_ID;
                $func->_avaliacao = $avaliacoes->get($func->FUNCIONARIO_ID);
                $func->_com_penalidade = isset($comPenalidade[$func->FUNCIONARIO_ID]);
                $cfg = $this->eleg->getProgConfig($func->CARREIRA_ID);
                $eleg = $this->eleg->avaliarEleg($func, $cfg);
                if (! $eleg['elegivel'] || ! $eleg['novo_vencimento']) {
                    continue;
                }
                $venc = $this->eleg->getVencBase($func);
                $anos = $func->FUNCIONARIO_DATA_INICIO ? (int) Carbon::now()->diffInYears(Carbon::parse($func->FUNCIONARIO_DATA_INICIO)) : 0;
                $anu = $cfg->CONFIG_ANUENIO_PCT / 100 * $anos;
                $salAt = $venc * (1 + $anu);
                $salNv = $eleg['novo_vencimento'] * (1 + $anu);
                $dif = round($salNv - $salAt, 2);
                $impTotal += $dif;
                $impactados++;
            }
        }

        $novaFolha = $folhaMes + $impTotal;
        $impAnual = $impTotal * 12;
        $despAnual = $novaFolha * 12;
        $pctLRF = $rcl > 0 ? round($despAnual / $rcl * 100, 2) : 0;
        $pctFolha = $folhaMes > 0 ? round($impTotal / $folhaMes * 100, 2) : 0;
        $statusLRF = $pctLRF >= 54 ? 'limite_excedido' : ($pctLRF >= 51.3 ? 'limite_prudencial' : ($pctLRF >= 48.6 ? 'alerta' : 'seguro'));

        $perDet = 50;

        return [
            'detalhes' => [],
            'detalhes_meta' => [
                'current_page' => 1,
                'last_page' => $impactados > 0 ? (int) ceil($impactados / $perDet) : 1,
                'per_page' => $perDet,
                'total' => $impactados,
                'endpoint' => '/api/v3/progressao-funcional/impacto/detalhes',
            ],
            'servidores_impactados' => $impactados,
            'impacto_mensal' => round($impTotal, 2),
            'impacto_anual' => round($impAnual, 2),
            'folha_atual' => round($folhaMes, 2),
            'nova_folha' => round($novaFolha, 2),
            'percentual_impacto_folha' => $pctFolha,
            'despesa_anual' => round($despAnual, 2),
            'rcl' => $rcl,
            'percentual_lrf' => $pctLRF,
            'status_lrf' => $statusLRF,
            'lrf_limites' => ['seguro' => 48.6, 'alerta' => 48.6, 'prudencial' => 51.3, 'maximo' => 54.0],
        ];
    }

    /**
     * @return array{detalhes: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function impactoDetalhesPagina(int $page, int $perPage): array
    {
        [$page, $perPage] = $this->normalizePagination($page, $perPage);
        $afterSkip = ($page - 1) * $perPage;
        $idx = 0;
        $out = [];
        $lastId = 0;

        while (true) {
            $chunk = $this->servidoresBaseQuery(null, null, false)
                ->where('f.FUNCIONARIO_ID', '>', $lastId)
                ->orderBy('f.FUNCIONARIO_ID')
                ->limit(self::CHUNK)
                ->get();
            if ($chunk->isEmpty()) {
                break;
            }
            $ids = $chunk->pluck('FUNCIONARIO_ID');
            $avaliacoes = $this->batchAvaliacoes($ids);
            $comPenalidade = $this->batchPenalidade($ids);

            foreach ($chunk as $func) {
                $lastId = (int) $func->FUNCIONARIO_ID;
                $func->_avaliacao = $avaliacoes->get($func->FUNCIONARIO_ID);
                $func->_com_penalidade = isset($comPenalidade[$func->FUNCIONARIO_ID]);
                $cfg = $this->eleg->getProgConfig($func->CARREIRA_ID);
                $eleg = $this->eleg->avaliarEleg($func, $cfg);
                if (! $eleg['elegivel'] || ! $eleg['novo_vencimento']) {
                    continue;
                }
                if ($idx >= $afterSkip && count($out) < $perPage) {
                    $venc = $this->eleg->getVencBase($func);
                    $anos = $func->FUNCIONARIO_DATA_INICIO ? (int) Carbon::now()->diffInYears(Carbon::parse($func->FUNCIONARIO_DATA_INICIO)) : 0;
                    $anu = $cfg->CONFIG_ANUENIO_PCT / 100 * $anos;
                    $salAt = $venc * (1 + $anu);
                    $salNv = $eleg['novo_vencimento'] * (1 + $anu);
                    $dif = round($salNv - $salAt, 2);
                    $out[] = [
                        'id' => $func->FUNCIONARIO_ID,
                        'nome' => $func->PESSOA_NOME,
                        'cargo' => $func->CARGO_NOME ?? '—',
                        'classe' => $func->FUNCIONARIO_CLASSE ?? '—',
                        'ref_atual' => $func->FUNCIONARIO_REFERENCIA ?? '—',
                        'ref_nova' => $eleg['proxima_referencia'],
                        'salario_atual' => round($salAt, 2),
                        'novo_salario' => round($salNv, 2),
                        'diferenca' => $dif,
                    ];
                }
                $idx++;
            }
        }

        return [
            'detalhes' => $out,
            'meta' => $this->meta($page, $perPage, $idx),
        ];
    }

    private function servidoresBaseQuery(?string $busca, ?int $setorId, bool $includeSetorScalar): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('CARREIRA as ca', 'ca.CARREIRA_ID', '=', 'f.CARREIRA_ID')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select('f.*', 'p.PESSOA_NOME', 'c.CARGO_NOME', 'c.CARGO_SALARIO', 'ca.CARREIRA_NOME', 'ca.CARREIRA_REGIME');

        if ($includeSetorScalar && Schema::hasTable('LOTACAO')) {
            $q->addSelect(DB::raw('(SELECT l.SETOR_ID FROM LOTACAO l WHERE l.FUNCIONARIO_ID = f.FUNCIONARIO_ID AND l.LOTACAO_DATA_FIM IS NULL ORDER BY l.LOTACAO_ID ASC LIMIT 1) as setor_id'));
        }

        $this->applyBusca($q, $busca);
        $this->applySetorFilter($q, $setorId);

        return $q;
    }

    private function applyBusca(\Illuminate\Database\Query\Builder $q, ?string $busca): void
    {
        $busca = $busca !== null ? trim($busca) : '';
        if ($busca === '') {
            return;
        }

        $onlyDigits = preg_replace('/\D/', '', $busca);
        if ($onlyDigits !== '' && $onlyDigits === $busca) {
            $q->where(function ($w) use ($onlyDigits) {
                $w->where('f.FUNCIONARIO_MATRICULA', $onlyDigits)
                    ->orWhere('f.FUNCIONARIO_MATRICULA', 'like', $onlyDigits . '%');
                foreach (['PESSOA_CPF_NUMERO', 'PESSOA_CPF'] as $col) {
                    if (Schema::hasColumn('PESSOA', $col)) {
                        // R39: remove crase MySQL — identificador sem quoting funciona em SQLite/MySQL/SQL Server
                        $w->orWhereRaw("REPLACE(REPLACE(REPLACE(COALESCE(p." . $col . ",''),'.',''),'-',''),' ','') like ?", [$onlyDigits . '%']);
                    }
                }
            });

            return;
        }

        $q->where('p.PESSOA_NOME', 'like', '%' . $busca . '%');
    }

    private function applySetorFilter(\Illuminate\Database\Query\Builder $q, ?int $setorId): void
    {
        if (! $setorId || ! Schema::hasTable('LOTACAO')) {
            return;
        }
        $q->whereExists(function ($s) use ($setorId) {
            $s->select(DB::raw(1))
                ->from('LOTACAO as lot')
                ->whereColumn('lot.FUNCIONARIO_ID', 'f.FUNCIONARIO_ID')
                ->whereNull('lot.LOTACAO_DATA_FIM')
                ->where('lot.SETOR_ID', $setorId);
        });
    }

    private function batchAvaliacoes(Collection $funcIds): Collection
    {
        if ($funcIds->isEmpty()) {
            return collect();
        }
        $ordAval = $this->eleg->pickAvaliacaoOrderCol();
        $qAval = DB::table('AVALIACAO_DESEMPENHO')->whereIn('FUNCIONARIO_ID', $funcIds);
        if ($ordAval) {
            $qAval->orderByDesc($ordAval);
        }

        return $qAval->get()->groupBy('FUNCIONARIO_ID')->map(fn ($g) => $g->first());
    }

    /**
     * @return array<int, int>
     */
    private function batchPenalidade(Collection $funcIds): array
    {
        if ($funcIds->isEmpty()) {
            return [];
        }

        return DB::table('AFASTAMENTO')
            ->whereIn('FUNCIONARIO_ID', $funcIds)
            ->whereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%disciplinar%' OR LOWER(AFASTAMENTO_TIPO) LIKE '%suspen%'")
            ->where(fn ($q) => $q->whereNull('AFASTAMENTO_DATA_FIM')->orWhere('AFASTAMENTO_DATA_FIM', '>=', now()))
            ->pluck('FUNCIONARIO_ID')->flip()->toArray();
    }
}
