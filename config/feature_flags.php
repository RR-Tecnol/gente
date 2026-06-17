<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags (P4)
    |--------------------------------------------------------------------------
    |
    | Flags para controlar exposição de recursos públicos sem alterar rota,
    | permitindo ativação por etapa institucional.
    |
    */
    'transparencia' => [
        'dossie_terceirizacao' => env('FF_TRANSPARENCIA_DOSSIE_TERCEIRIZACAO', true),
        'observabilidade_integracoes' => env('FF_TRANSPARENCIA_OBSERVABILIDADE', true),
        'catalogo_dados' => env('FF_TRANSPARENCIA_CATALOGO_DADOS', true),
    ],
];

