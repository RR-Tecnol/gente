<?php

namespace App\Support\Tenancy;

class TenantContext
{
    public function __construct(
        public ?string $tenantId = null,
        public string $source = 'none'
    ) {
    }

    public function resolved(): bool
    {
        return $this->tenantId !== null && $this->tenantId !== '';
    }
}

