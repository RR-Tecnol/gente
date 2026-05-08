<?php

namespace App\Http\Middleware;

use App\Events\HoneytokenTriggered;
use App\Security\HoneytokenRegistry;
use Closure;
use Illuminate\Http\Request;

/**
 * Dispara se o caminho aponta para /funcionarios/{id} isca ou se o body/query
 * contém chaves *funcionario*id (ex.: funcionario_id, origem_funcionario_id).
 */
class HoneytokenTripwire
{
    public function handle(Request $request, Closure $next)
    {
        if (! (bool) config('gente.honeytokens.enabled', true)) {
            return $next($request);
        }

        $honeyIds = HoneytokenRegistry::honeyFuncionarioIds();
        if ($honeyIds === []) {
            return $next($request);
        }
        $set = array_fill_keys($honeyIds, true);

        foreach ($this->collectFuncionarioRefIds($request) as $n) {
            if (isset($set[$n])) {
                event(new HoneytokenTriggered('honey_funcionario', $request, $n));

                return response()->json([
                    'erro' => 'Recurso indisponível.',
                    'code' => 'GENTE_HONEYTOKEN',
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * @return list<int>
     */
    private function collectFuncionarioRefIds(Request $request): array
    {
        $ids = [];
        $path = $request->getPathInfo() ?: '/';
        if (preg_match('#/api/v3/funcionarios/(\d+)(?:/|$)#', $path, $m)) {
            $ids[] = (int) $m[1];
        }
        $merged = array_merge(
            $request->query->all(),
            $request->request->all()
        );
        if ($request->isJson() && is_array($request->json()->all())) {
            $merged = array_merge($merged, $request->json()->all());
        }
        $this->extractFuncionarioKeys($merged, $ids);

        return array_values(array_unique(array_filter($ids, fn ($x) => $x > 0)));
    }

    private function extractFuncionarioKeys($data, array &$ids): void
    {
        if (! is_array($data)) {
            return;
        }
        foreach ($data as $k => $v) {
            if (is_string($k)) {
                $kl = mb_strtolower($k, 'UTF-8');
                if ((strpos($kl, 'funcionario') !== false && (substr($kl, -2) === 'id' || $kl === 'funcionarioid'))
                    || $kl === 'funcionario_id'
                ) {
                    if (is_int($v)) {
                        $ids[] = $v;
                    } elseif (is_string($v) && ctype_digit($v) && strlen($v) <= 10) {
                        $ids[] = (int) $v;
                    }
                }
            }
            if (is_array($v)) {
                $this->extractFuncionarioKeys($v, $ids);
            }
        }
    }
}
