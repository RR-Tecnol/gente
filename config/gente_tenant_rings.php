<?php

/**
 * Manta de Proteção Global (Onda 0) — anéis por prefixo de path.
 *
 * Variáveis de ambiente:
 * - GENTE_TENANT_SCOPE_MIDDLEWARE: activa o middleware (default false).
 * - GENTE_TENANT_SCOPE_ENFORCE: se true, bloqueia/clampa; se false, só shadow log.
 * - GENTE_TENANT_SCOPE_LOG_CHANNEL: nome do canal em config/logging.php (default tenant_scope).
 */
return [
    'middleware_enabled' => (bool) env('GENTE_TENANT_SCOPE_MIDDLEWARE', false),
    'enforce' => (bool) env('GENTE_TENANT_SCOPE_ENFORCE', false),
    'log_channel' => (string) env('GENTE_TENANT_SCOPE_LOG_CHANNEL', 'tenant_scope'),

    'exclude_path_prefixes' => [
        'api/v3/health',
        'api/v3/auth',
    ],

    'rings' => [
        'operacional_escala_freq' => [
            'path_prefixes' => [
                'api/v3/escala-trabalho',
                'api/v3/escala-saude',
            ],
            'anchor_priority' => ['setor_id', 'unidade_id', 'funcionario_id'],
            'require_anchor_for_mutations' => true,
            'max_per_page' => 200,
            'semad_block_mutations' => true,
        ],
        'rh_ciclo_vida' => [
            'path_prefixes' => [
                'api/v3/funcionarios',
                'api/v3/progressao-funcional',
                'api/v3/contratos',
            ],
            'anchor_priority' => ['funcionario_id', 'setor_id', 'unidade_id'],
            'require_anchor_for_mutations' => true,
            'max_per_page' => 100,
            'semad_block_mutations' => true,
        ],
    ],
];
