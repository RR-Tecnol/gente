<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-tenant readiness (P6)
    |--------------------------------------------------------------------------
    |
    | Modo inicial sem troca de conexão: apenas resolução de contexto de tenant
    | para preparar expansão multi-município de forma gradual.
    |
    */
    'enabled' => (bool) env('TENANCY_ENABLED', false),

    // Estratégia de resolução: subdomain | header
    'resolver' => env('TENANCY_RESOLVER', 'subdomain'),

    // Header usado quando resolver=header
    'header_name' => env('TENANCY_HEADER', 'X-Tenant-Id'),

    // Subdomínios que não representam tenant
    'reserved_subdomains' => ['www', 'api', 'admin'],
];

