<?php

namespace App\Support\TenantScope;

use App\Models\Usuario;
use App\Support\GenteSudoGlobalView;
use App\Support\RbacResolver;
use Illuminate\Http\Request;

/**
 * Orquestra RBAC (uma vez por request), SEMAD, modo global e violações de âncora/escopo.
 *
 * A regra SEMAD para mutações está centralizada aqui; {@see \App\Http\Middleware\SemadEscalaReadOnly}
 * permanece nas rotas de escala até o enforce da Manta estar estável em produção — depois pode
 * retirar-se gradualmente em favor deste fluxo único.
 */
final class TenantScopeEvaluator
{
    /** @var RbacResolver|null */
    private $rbac;

    public function __construct(?RbacResolver $rbac = null)
    {
        $this->rbac = $rbac;
    }

    /**
     * @param array<string, mixed> $ring
     * @param array<string, mixed> $anchors ver {@see TenantScopeAnchorResolver}
     */
    public function evaluate(
        Request $request,
        Usuario $user,
        string $ringKey,
        array $ring,
        array $anchors
    ): TenantScopeDecision {
        $t0 = microtime(true);
        $path = TenantScopePolicyRegistry::normalizePath($request);
        $method = strtoupper($request->method());
        $usuarioId = (int) $user->getAttribute('USUARIO_ID');
        $enforce = (bool) config('gente_tenant_rings.enforce', false);

        $globalLegitimate = GenteSudoGlobalView::podeUsarVisaoGlobal($user, $request);
        if ($globalLegitimate) {
            GenteSudoGlobalView::auditarAcessoGlobalSeAplicavel($user, $request);
        }
        $scopeMode = $globalLegitimate ? 'global' : 'tenant';

        $isMutation = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $semadBlock = (bool) ($ring['semad_block_mutations'] ?? false);
        $semadWouldBlock = false;
        if ($isMutation && $semadBlock) {
            $resolver = $this->resolver();
            $semadWouldBlock = $resolver->usuarioTemPapelSemadAuditoria($usuarioId);
        }

        $rbacSkipped = false;
        $allowedIds = [];
        $skipScopeForGlobalGet = $globalLegitimate && ! $isMutation;

        if (! $skipScopeForGlobalGet) {
            $attrKey = 'gente.tenant.allowed_unidade_ids';
            if ($request->attributes->has($attrKey)) {
                $cached = $request->attributes->get($attrKey);
                $allowedIds = is_array($cached) ? array_values(array_map('intval', $cached)) : [];
            } else {
                $resolver = $this->resolver();
                $allowedIds = $resolver->unidadeIdsDoEscopoOperacional($usuarioId);
                $request->attributes->set($attrKey, $allowedIds);
            }
        } else {
            $rbacSkipped = true;
        }

        $violations = [];
        if ($semadWouldBlock) {
            $violations[] = 'SEMAD_READ_ONLY';
        }

        $requireAnchor = (bool) ($ring['require_anchor_for_mutations'] ?? false);
        $hasAnchor = $this->hasEffectiveAnchor($anchors);
        $resolvedUnidade = isset($anchors['resolved_unidade_id']) ? $anchors['resolved_unidade_id'] : null;
        if ($isMutation && $requireAnchor) {
            if (! $hasAnchor) {
                $violations[] = 'ANCHOR_MISSING';
            } elseif ($resolvedUnidade === null || $resolvedUnidade <= 0) {
                $violations[] = 'ANCHOR_MISSING';
            }
        }

        if (! $skipScopeForGlobalGet && $hasAnchor && $resolvedUnidade !== null && $resolvedUnidade > 0) {
            if ($allowedIds === [] || ! in_array((int) $resolvedUnidade, $allowedIds, true)) {
                $violations[] = 'OUT_OF_SCOPE';
            }
        }

        $maxPer = isset($ring['max_per_page']) ? (int) $ring['max_per_page'] : 0;
        $perPageIn = null;
        $perPageOut = null;
        $wouldClamp = false;
        if ($method === 'GET' && $maxPer > 0 && $request->query->has('per_page')) {
            $perPageIn = $this->coercePositiveInt($request->query->get('per_page'));
            if ($perPageIn !== null && $perPageIn > $maxPer) {
                $wouldClamp = true;
                $perPageOut = $maxPer;
                $violations[] = 'PAGINATION_EXCEEDED';
            }
        }

        $virtual = $this->resolveVirtualStatus($violations);

        $duration = (microtime(true) - $t0) * 1000.0;

        $decision = new TenantScopeDecision(
            $ringKey,
            $path,
            $method,
            $usuarioId > 0 ? $usuarioId : null,
            $scopeMode,
            (string) ($anchors['anchor_source'] ?? 'none'),
            isset($anchors['raw_setor_id']) ? $anchors['raw_setor_id'] : null,
            isset($anchors['raw_unidade_id']) ? $anchors['raw_unidade_id'] : null,
            isset($anchors['raw_funcionario_id']) ? $anchors['raw_funcionario_id'] : null,
            isset($anchors['resolved_setor_id']) ? $anchors['resolved_setor_id'] : null,
            isset($anchors['resolved_unidade_id']) ? $anchors['resolved_unidade_id'] : null,
            $allowedIds,
            $globalLegitimate,
            $semadWouldBlock,
            array_values(array_unique($violations)),
            $virtual,
            $enforce,
            $wouldClamp,
            $perPageIn,
            $perPageOut,
            $rbacSkipped,
            round($duration, 3)
        );

        $request->attributes->set('gente.tenant.decision', $decision);

        return $decision;
    }

    /**
     * @param list<string> $violations
     */
    private function resolveVirtualStatus(array $violations): int
    {
        if (in_array('SEMAD_READ_ONLY', $violations, true)) {
            return 403;
        }
        if (in_array('ANCHOR_MISSING', $violations, true)) {
            return 422;
        }
        if (in_array('OUT_OF_SCOPE', $violations, true)) {
            return 403;
        }

        return 200;
    }

    /**
     * @param array<string, mixed> $anchors
     */
    private function hasEffectiveAnchor(array $anchors): bool
    {
        foreach (['resolved_unidade_id', 'resolved_setor_id', 'raw_unidade_id', 'raw_setor_id', 'raw_funcionario_id'] as $k) {
            if (! isset($anchors[$k])) {
                continue;
            }
            $v = $anchors[$k];
            if (is_int($v) && $v > 0) {
                return true;
            }
        }

        return false;
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

    private function resolver(): RbacResolver
    {
        if ($this->rbac === null) {
            $this->rbac = new RbacResolver();
        }

        return $this->rbac;
    }
}
