<?php

namespace App\Support\TenantScope;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Extrai âncoras (setor / unidade / funcionário) e resolve unidade canónica (setor → UNIDADE_ID; lotação activa).
 *
 * @phpstan-type AnchorBag array{
 *   anchor_source: string,
 *   raw_setor_id: int|null,
 *   raw_unidade_id: int|null,
 *   raw_funcionario_id: int|null,
 *   resolved_setor_id: int|null,
 *   resolved_unidade_id: int|null,
 * }
 */
final class TenantScopeAnchorResolver
{
    /**
     * @param array<string, mixed> $ring
     *
     * @return AnchorBag
     */
    public function resolveAnchors(Request $request, string $ringKey, array $ring): array
    {
        $path = TenantScopePolicyRegistry::normalizePath($request);
        $bag = $this->mergeInputBag($request, $path);

        $priority = (array) ($ring['anchor_priority'] ?? ['setor_id', 'unidade_id', 'funcionario_id']);
        $rawSetor = null;
        $rawUnidade = null;
        $rawFuncionario = null;
        $anchorSource = 'none';

        foreach ($priority as $key) {
            $k = (string) $key;
            if ($k === 'setor_id') {
                $v = $this->pickInt($bag, ['setor_id', 'SETOR_ID']);
                if ($v !== null) {
                    $rawSetor = $v;
                    $anchorSource = 'setor_id';
                    break;
                }
            } elseif ($k === 'unidade_id') {
                $v = $this->pickInt($bag, ['unidade_id', 'UNIDADE_ID']);
                if ($v !== null) {
                    $rawUnidade = $v;
                    $anchorSource = 'unidade_id';
                    break;
                }
            } elseif ($k === 'funcionario_id') {
                $v = $this->pickInt($bag, ['funcionario_id', 'FUNCIONARIO_ID']);
                if ($v !== null) {
                    $rawFuncionario = $v;
                    $anchorSource = 'funcionario_id';
                    break;
                }
            }
        }

        $resolvedSetor = $rawSetor;
        $resolvedUnidade = $rawUnidade;

        if ($resolvedUnidade === null && $resolvedSetor !== null) {
            $resolvedUnidade = $this->unidadeIdFromSetor($request, $resolvedSetor);
        }

        if (($resolvedSetor === null || $resolvedUnidade === null) && $rawFuncionario !== null) {
            $lot = $this->lotacaoAtivaPorFuncionario($request, $rawFuncionario);
            if ($lot !== null) {
                if ($resolvedSetor === null && isset($lot['setor_id'])) {
                    $resolvedSetor = (int) $lot['setor_id'];
                }
                if ($resolvedUnidade === null && isset($lot['unidade_id'])) {
                    $resolvedUnidade = (int) $lot['unidade_id'];
                }
                if ($anchorSource === 'funcionario_id' && ($rawSetor === null && $rawUnidade === null)) {
                    $anchorSource = 'lotacao_fallback';
                }
            }
        }

        return [
            'anchor_source' => $anchorSource,
            'raw_setor_id' => $rawSetor,
            'raw_unidade_id' => $rawUnidade,
            'raw_funcionario_id' => $rawFuncionario,
            'resolved_setor_id' => $resolvedSetor,
            'resolved_unidade_id' => $resolvedUnidade,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeInputBag(Request $request, string $path): array
    {
        $routeParams = $request->route() ? $request->route()->parameters() : [];
        if (isset($routeParams['id']) && Str::startsWith($path, 'api/v3/funcionarios')) {
            if (! isset($routeParams['funcionario_id'])) {
                $routeParams['funcionario_id'] = $routeParams['id'];
            }
        }

        $merged = array_merge(
            $request->query->all(),
            $request->request->all(),
            $routeParams
        );
        if ($request->isJson()) {
            $json = $request->json()->all();
            if (is_array($json)) {
                $merged = array_merge($merged, $json);
            }
        }

        return $merged;
    }

    /**
     * @param list<string> $keys
     */
    private function pickInt(array $bag, array $keys): ?int
    {
        $flat = [];
        foreach ($bag as $k => $v) {
            $flat[is_string($k) ? strtolower((string) $k) : $k] = $v;
        }
        foreach ($keys as $key) {
            $lk = strtolower($key);
            if (! array_key_exists($lk, $flat)) {
                continue;
            }
            $n = $this->coercePositiveInt($flat[$lk]);
            if ($n !== null) {
                return $n;
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function coercePositiveInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_numeric($value)) {
            $i = (int) $value;

            return $i > 0 ? $i : null;
        }

        return null;
    }

    private function unidadeIdFromSetor(Request $request, int $setorId): ?int
    {
        $cacheKey = 'gente.tenant.setor_unidade.'.$setorId;
        if ($request->attributes->has($cacheKey)) {
            $c = $request->attributes->get($cacheKey);

            return is_int($c) ? $c : null;
        }
        if (! Schema::hasTable('SETOR')) {
            $request->attributes->set($cacheKey, null);

            return null;
        }
        $uid = DB::table('SETOR')->where('SETOR_ID', $setorId)->value('UNIDADE_ID');
        $out = $uid !== null ? (int) $uid : null;
        $request->attributes->set($cacheKey, $out);

        return $out;
    }

    /**
     * @return array{setor_id: int, unidade_id: int}|null
     */
    private function lotacaoAtivaPorFuncionario(Request $request, int $funcionarioId): ?array
    {
        $cacheKey = 'gente.tenant.lotacao_fallback.'.$funcionarioId;
        if ($request->attributes->has($cacheKey)) {
            $c = $request->attributes->get($cacheKey);

            return is_array($c) ? $c : null;
        }
        if (! Schema::hasTable('LOTACAO') || ! Schema::hasTable('SETOR')) {
            $request->attributes->set($cacheKey, null);

            return null;
        }
        $row = DB::table('LOTACAO as l')
            ->join('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->where('l.FUNCIONARIO_ID', $funcionarioId)
            ->whereNull('l.LOTACAO_DATA_FIM')
            ->orderByDesc('l.LOTACAO_ID')
            ->select(['l.SETOR_ID', 's.UNIDADE_ID'])
            ->first();
        if (! $row) {
            $row = DB::table('LOTACAO as l')
                ->join('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->where('l.FUNCIONARIO_ID', $funcionarioId)
                ->orderByDesc('l.LOTACAO_ID')
                ->select(['l.SETOR_ID', 's.UNIDADE_ID'])
                ->first();
        }
        if (! $row) {
            $request->attributes->set($cacheKey, null);

            return null;
        }
        $sid = (int) $row->SETOR_ID;
        $uid = $this->unidadeIdFromSetor($request, $sid);
        if ($uid === null || $uid <= 0) {
            $request->attributes->set($cacheKey, null);

            return null;
        }
        $out = [
            'setor_id' => $sid,
            'unidade_id' => $uid,
        ];
        $request->attributes->set($cacheKey, $out);

        return $out;
    }
}
