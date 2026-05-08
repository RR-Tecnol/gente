<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Support\TenantScope\TenantScopeAnchorResolver;
use App\Support\TenantScope\TenantScopeEvaluator;
use App\Support\TenantScope\TenantScopePolicyRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EnsureTenantScope
{
    /** @var TenantScopePolicyRegistry */
    private $registry;

    /** @var TenantScopeAnchorResolver */
    private $anchors;

    /** @var TenantScopeEvaluator */
    private $evaluator;

    public function __construct(
        TenantScopePolicyRegistry $registry,
        TenantScopeAnchorResolver $anchors,
        TenantScopeEvaluator $evaluator
    ) {
        $this->registry = $registry;
        $this->anchors = $anchors;
        $this->evaluator = $evaluator;
    }

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        if (! (bool) config('gente_tenant_rings.middleware_enabled', false)) {
            return $next($request);
        }

        $user = Auth::user();
        if (! $user instanceof Usuario) {
            return $next($request);
        }

        if ($this->registry->isExcludedPath($request)) {
            $request->attributes->set('gente.tenant.skipped', 'excluded_path');

            return $next($request);
        }

        $matched = $this->registry->matchRing($request);
        if ($matched === null) {
            $request->attributes->set('gente.tenant.skipped', 'no_ring');

            return $next($request);
        }

        $ringKey = $matched['key'];
        $ring = $matched['ring'];
        $request->attributes->set('gente.tenant.ring', $ringKey);

        $anchorPayload = $this->anchors->resolveAnchors($request, $ringKey, $ring);
        $request->attributes->set('gente.tenant.anchors', $anchorPayload);

        $decision = $this->evaluator->evaluate($request, $user, $ringKey, $ring, $anchorPayload);

        $channel = (string) config('gente_tenant_rings.log_channel', 'tenant_scope');

        if (! $decision->enforce) {
            try {
                Log::channel($channel)->info('tenant_scope.shadow', $decision->toLogContext());
            } catch (\Throwable $e) {
                // não impedir a requisição se o canal falhar
            }

            return $next($request);
        }

        if ($decision->virtual_status === 403) {
            if (in_array('SEMAD_READ_ONLY', $decision->violations, true)) {
                return response()->json([
                    'ok' => false,
                    'erro' => 'Perfil SEMAD (auditoria matriz): operação não permitida neste domínio.',
                    'code' => 'SEMAD_READ_ONLY',
                ], 403);
            }
            if (in_array('OUT_OF_SCOPE', $decision->violations, true)) {
                return response()->json([
                    'ok' => false,
                    'erro' => 'Recurso fora do escopo operacional permitido para o utilizador.',
                    'code' => 'OUT_OF_SCOPE',
                ], 403);
            }

            return response()->json([
                'ok' => false,
                'erro' => 'Acesso negado.',
                'code' => 'TENANT_SCOPE_DENIED',
            ], 403);
        }

        if ($decision->virtual_status === 422) {
            return response()->json([
                'ok' => false,
                'erro' => 'Parâmetro de âncora (setor/unidade/funcionário) em falta ou inválido para esta operação.',
                'code' => 'ANCHOR_MISSING',
            ], 422);
        }

        if ($decision->would_clamp_per_page && $decision->per_page_out !== null) {
            $request->query->set('per_page', (string) $decision->per_page_out);
        }

        return $next($request);
    }
}
