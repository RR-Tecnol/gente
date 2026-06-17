<?php

namespace App\Services\Smoke;

/**
 * Opções do smoke Fase 7A (CLI e testes reutilizam a mesma estrutura).
 */
final class SmokeTeiaFolhaOptions
{
    public function __construct(
        public readonly ?int $funcionarioId = null,
        public readonly ?int $folhaId = null,
        public readonly ?string $competencia = null,
        /** Se true, inspeciona tenant_scope.log (fluxo 4b); não substitui request HTTP real. */
        public readonly bool $checkTenantScopeLog = false,
    ) {}
}
