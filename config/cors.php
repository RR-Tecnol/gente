<?php
return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | GENTE v3 — Sprint 0 TASK-02
    | supports_credentials=true: necessário para sessão cookie-based (SPA Vue)
    | allowed_origins: explícito — nunca usar wildcard com credentials=true
    |
    */

    'paths' => ['api/*', 'csrf-cookie', 'sanctum/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://127.0.0.1:5173',
        'http://localhost:5173',
        'http://127.0.0.1:8000',
        'http://localhost:8000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
