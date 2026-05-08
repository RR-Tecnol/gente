<?php

return [
    /*
    |--------------------------------------------------------------------------
    | P4 — Política e toggles de transparência pública
    |--------------------------------------------------------------------------
    | Trilha de revisão: alinhar com parecer PGM e `transparencia_catalogo.php`.
    | Os toggles abaixo espelham `config/feature_flags.php` (transparência).
    */
    'politica' => [
        'versao' => env('TRANSPARENCIA_POLITICA_VERSAO', '2026-04-28'),
        'owner' => env('TRANSPARENCIA_POLICY_OWNER', 'SEMAD'),
    ],
    'toggles' => [
        'dossie_terceirizacao' => env('FF_TRANSPARENCIA_DOSSIE_TERCEIRIZACAO', true),
        'observabilidade_integracoes' => env('FF_TRANSPARENCIA_OBSERVABILIDADE', true),
        'catalogo_dados' => env('FF_TRANSPARENCIA_CATALOGO_DADOS', true),
    ],
];
