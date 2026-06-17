<?php

namespace App\Support\TenantScope;

/**
 * Resultado imutável do dry-run de tenant scope (shadow ou enforce).
 *
 * @param list<int> $allowed_unidade_ids
 * @param list<string> $violations
 */
final class TenantScopeDecision
{
    public function __construct(
        public ?string $ring_key,
        public string $path,
        public string $method,
        public ?int $usuario_id,
        public string $scope_mode,
        public string $anchor_source,
        public ?int $raw_setor_id,
        public ?int $raw_unidade_id,
        public ?int $raw_funcionario_id,
        public ?int $resolved_setor_id,
        public ?int $resolved_unidade_id,
        public array $allowed_unidade_ids,
        public bool $global_scope_legitimate,
        public bool $semad_would_block,
        public array $violations,
        public int $virtual_status,
        public bool $enforce,
        public bool $would_clamp_per_page,
        public ?int $per_page_in,
        public ?int $per_page_out,
        public bool $rbac_query_skipped,
        public ?float $duration_ms
    ) {
    }

    /**
     * Contexto estável para Log::channel(...)->info('tenant_scope.shadow', $ctx).
     *
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        $allowed = $this->allowed_unidade_ids;
        $allowedTrunc = $allowed;
        $allowedCount = count($allowed);
        $maxIds = 50;
        if ($allowedCount > $maxIds) {
            $allowedTrunc = array_slice($allowed, 0, $maxIds);
        }

        return [
            'event' => 'tenant_scope.shadow',
            'ring_key' => $this->ring_key,
            'path' => $this->path,
            'method' => $this->method,
            'usuario_id' => $this->usuario_id,
            'scope_mode' => $this->scope_mode,
            'anchor_source' => $this->anchor_source,
            'raw_setor_id' => $this->raw_setor_id,
            'raw_unidade_id' => $this->raw_unidade_id,
            'raw_funcionario_id' => $this->raw_funcionario_id,
            'resolved_setor_id' => $this->resolved_setor_id,
            'resolved_unidade_id' => $this->resolved_unidade_id,
            'allowed_unidade_ids' => $allowedTrunc,
            'allowed_unidade_ids_count' => $allowedCount,
            'global_scope_legitimate' => $this->global_scope_legitimate,
            'semad_would_block' => $this->semad_would_block,
            'violations' => $this->violations,
            'virtual_status' => $this->virtual_status,
            'enforce' => $this->enforce,
            'would_clamp_per_page' => $this->would_clamp_per_page,
            'per_page_in' => $this->per_page_in,
            'per_page_out' => $this->per_page_out,
            'rbac_query_skipped' => $this->rbac_query_skipped,
            'duration_ms' => $this->duration_ms,
        ];
    }
}
