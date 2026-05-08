<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shadow Deployment (P3)
    |--------------------------------------------------------------------------
    |
    | Configurações para execução de ETL cego, cálculo e diff matemático
    | em ambiente de homologação.
    |
    */
    'snapshot_root' => env('SHADOW_SNAPSHOT_ROOT', storage_path('app/shadow')),

    'queues' => [
        'etl' => env('SHADOW_QUEUE_ETL', 'queue-shadow-etl'),
        'calc' => env('SHADOW_QUEUE_CALC', 'queue-shadow-calc'),
        'diff' => env('SHADOW_QUEUE_DIFF', 'queue-shadow-diff'),
    ],

    'chunk_size' => (int) env('SHADOW_CHUNK_SIZE', 500),

    'limiar_tolerancia_rs' => env('SHADOW_LIMIAR_RS', '0.03'),
];

