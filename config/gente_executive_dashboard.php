<?php

/**
 * Fase 9A — Painel executivo (KPIs agregados para alta gestão / TCE-MA).
 */
return [
    /** TTL em segundos para Cache::remember na rota operacional */
    'cache_ttl_seconds' => max(30, (int) env('GENTE_EXEC_DASHBOARD_CACHE_TTL', 90)),

    /**
     * Siglas de UNIDADE cujo organograma conta para “elegível MDE” (educação municipal — São Luís: SEMED).
     * CSV no .env sobrepõe o default.
     */
    'mde_unidade_siglas' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GENTE_EXEC_DASHBOARD_MDE_SIGLAS', 'SEMED'))
    ))),

    /** Se true, ignora cálculo de furo e devolve taxa_furo_escala fixa (homologação apenas). */
    'mock_taxa_furo' => (bool) env('GENTE_EXEC_DASHBOARD_MOCK_FURO', false),

    'mock_taxa_furo_valor' => (float) env('GENTE_EXEC_DASHBOARD_MOCK_FURO_VALOR', 0.0),
];
