<?php

return [
    /*
    |--------------------------------------------------------------------------
    | P5-Standby — conectores externos (desligados por defeito)
    |--------------------------------------------------------------------------
    | Plano: integrações opcionais só com gatilho contratual. Cada conector
    | deve ter feature flag e runbook de ativação.
    */
    'conectores' => [
        'esocial_envio' => [
            'descricao' => 'Envio de eventos eSocial (produção restrita)',
            'habilitado' => (bool) env('P5_ESOCIAL_ENVIO', false),
        ],
        'cnab_bancario' => [
            'descricao' => 'Geração/envio de CNAB para bancos',
            'habilitado' => (bool) env('P5_CNAB_BANCARIO', false),
        ],
        'api_terceira_folha' => [
            'descricao' => 'Sincronização com folha/sistema terceiro',
            'habilitado' => (bool) env('P5_API_TERCEIRA_FOLHA', false),
        ],
    ],
];
