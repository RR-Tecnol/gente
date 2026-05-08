<?php

namespace App\Http\Controllers\Api\V3;

use App\Services\Dashboard\DashboardOperacionalService;
use App\Support\RbacResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardOperacionalController extends Controller
{
    public function __invoke(Request $request, DashboardOperacionalService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['erro' => 'Não autenticado.'], 401);
        }

        $usuarioId = (int) ($user->USUARIO_ID ?? $user->getAuthIdentifier() ?? 0);
        if ($usuarioId <= 0) {
            return response()->json(['erro' => 'Sessão inválida.'], 401);
        }

        $rbac = new RbacResolver;
        $ok = $rbac->can($usuarioId, 'global.mde.25') || $rbac->can($usuarioId, 'unidade.dashboard.kpi');
        if (! $ok) {
            return response()->json([
                'erro' => 'Acesso negado ao painel executivo.',
                'code' => 'dashboard_operacional_rbac',
            ], 403);
        }

        $data = (string) $request->query('data', now()->toDateString());
        $regiao = $request->query('regiao');
        $regiaoStr = is_string($regiao) && trim($regiao) !== '' ? trim($regiao) : null;

        $ttl = (int) config('gente_executive_dashboard.cache_ttl_seconds', 90);
        $cacheKey = 'gente:dashboard_operacional:v1:'.$data.':'.($regiaoStr ?? '');

        $payload = Cache::remember($cacheKey, $ttl, function () use ($service, $data, $regiaoStr) {
            return $service->build($data, $regiaoStr);
        });

        return response()->json($payload);
    }
}
