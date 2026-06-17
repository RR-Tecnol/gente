<?php

/**
 * Fase 8A — Carga Mestra SISFOLHA → GENTE (staging + promoção controlada).
 */
return [
    /** SETOR_ID fixo de quarentena (opcional). Se null, resolve via unidade/sigla abaixo. */
    'quarentena_setor_id' => env('GENTE_QUARENTENA_SETOR_ID'),

    /** Unidade de quarentena (padrão alinhado ao SuperSeederEstresseMigracao). */
    'quarentena_unidade_sigla' => env('GENTE_QUARENTENA_UNIDADE_SIGLA', 'MIG-NAO-CLASS'),
    'quarentena_setor_nome' => env('GENTE_QUARENTENA_SETOR_NOME', 'A CLASSIFICAR (import)'),

    /** Tamanho do chunk na promoção (apply). */
    'chunk_size' => (int) env('GENTE_SISFOLHA_IMPORT_CHUNK', 250),

    /** USUARIO_ID do operador para AUDIT_LOG em CLI (opcional). */
    'operator_usuario_id' => env('GENTE_SISFOLHA_IMPORT_OPERATOR_ID'),

    /** Senha inicial hash para novos USUARIO criados pelo import (definir em staging). */
    'default_password_hash' => env('GENTE_SISFOLHA_IMPORT_DEFAULT_PASSWORD_HASH'),
];
