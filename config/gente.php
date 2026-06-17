<?php

return [
    /*
     * Escala de trabalho / Kanban: quem vê tudo a nível município (sem filtro USUARIO_UNIDADE).
     * 1=Desenvolvedor, 2=Administrador, 7=Gestão, 13=Equipe SISGEP (alinhado a PerfilEnum + homolog).
     * Sobrescreva via GENTE_ESCALA_VISAO_GLOBAL_PERFIL_IDS="1,2,7,13" no .env
     */
    'escala' => [
        'visao_global_perfil_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('GENTE_ESCALA_VISAO_GLOBAL_PERFIL_IDS', '1,2,7,13'))
        ), fn (int $v) => $v > 0)),
        'kanban_incluir_elegiveis_sem_detalhe' => (bool) env('GENTE_ESCALA_KANBAN_INCLUIR_ELEGIVEIS', true),
        /** Limite 0 = sem teto (homolog; cuidado com 30k linhas no JSON) */
        'kanban_elegiveis_max' => max(0, (int) env('GENTE_ESCALA_KANBAN_ELEGIVEIS_MAX', 0)),
    ],
    /**
     * Sudo / visão global: whitelist fora de tabelas mutáveis de perfil (IDs e e-mails de login).
     * O bypass de tenant na API exige ainda o cabeçalho X-Gente-Global-View (ver gente.sudo_global_view).
     */
    'super_admins' => [
        'usuario_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('GENTE_SUPER_ADMIN_USUARIO_IDS', ''))
        ), fn (int $v) => $v > 0)),
        'emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GENTE_SUPER_ADMIN_EMAILS', ''))
        ))),
    ],
    'sudo_global_view' => [
        'enabled' => (bool) env('GENTE_SUDO_GLOBAL_VIEW_ENABLED', true),
        'header' => (string) env('GENTE_SUDO_GLOBAL_HEADER', 'X-Gente-Global-View'),
    ],
    /** Fase 8A — acúmulo: SPA envia o FUNCIONARIO_ID activo para APIs que exigem âncora (opcional). */
    'funcionario_context' => [
        'header' => (string) env('GENTE_FUNCIONARIO_CONTEXT_HEADER', 'X-Gente-Funcionario-Context-Id'),
    ],
    /**
     * PII / LGPD: FLE (campo) + blind index. Salt dedicado (não usar APP_KEY sozinha).
     * GENTE_PII_CPF_ENCRYPTED: após gente:secure-pii, ativar true (dados cifrados com APP_KEY no BD).
     */
    'pii' => [
        'blind_salt' => (string) env('GENTE_PII_BLIND_SALT', ''),
        'cpf_field_encrypted' => (bool) env('GENTE_PII_CPF_ENCRYPTED', false),
        /** true = CPF some do array/JSON (use makeVisible() onde for obrigatório) */
        'model_hide_cpf' => (bool) env('GENTE_PII_MODEL_HIDE_CPF', false),
    ],
    /**
     * Frente 2: HMAC do corpo + timestamp (cabeçalhos X-Gente-Signature, X-Gente-Timestamp).
     * Requer segredo de sessão (devolvido em /me quando ativo). Desligado por defeito.
     */
    'request_signature' => [
        'enabled' => (bool) env('GENTE_REQUEST_SIGNATURE_ENABLED', false),
        'leeway_ms' => (int) env('GENTE_REQUEST_SIGNATURE_LEEWAY_MS', 30000),
        'session_key' => 'gente_request_signing_secret',
    ],
    /** Frente 3: iscas (honeytokens) + canário + blocklist de IP */
    'honeytokens' => [
        'enabled' => (bool) env('GENTE_HONEYTOKENS_ENABLED', true),
        'id_cache_sec' => (int) env('GENTE_HONEYTOKEN_ID_CACHE', 300),
        'blocklist_enforce' => (bool) env('GENTE_HONEY_BLOCKLIST_ENFORCE', true),
        'blocklist_canary_24h' => (bool) env('GENTE_HONEY_BLOCKLIST_CANARY_24H', true),
        'blocklist_on_honey_touch' => (bool) env('GENTE_HONEY_BLOCKLIST_ON_TOUCH', false),
    ],
    /**
     * Frente 4: trilha imutável (Eloquent) + corrente de hash + exportação.
     */
    'audit_log' => [
        'immutability' => (bool) env('GENTE_AUDIT_LOG_IMMUTABLE', true),
        'chain_enabled' => (bool) env('GENTE_AUDIT_CHAIN', true),
    ],
    'tarpit' => [
        'enabled' => (bool) env('GENTE_TARPIT_ENABLED', true),
        'window_sec' => (int) env('GENTE_TARPIT_WINDOW_SEC', 60),
        '4xx_threshold' => (int) env('GENTE_TARPIT_4XX_THRESHOLD', 5),
        'penalty_ttl_sec' => (int) env('GENTE_TARPIT_PENALTY_TTL_SEC', 600),
        'max_sleep_sec' => (int) env('GENTE_TARPIT_MAX_SLEEP_SEC', 16),
        'skip_path_prefixes' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GENTE_TARPIT_SKIP', 'up,health,horizon,telescope'))
        ))),
    ],
    'secure_vault' => [
        'enabled' => (bool) env('GENTE_AUDIT_VAULT_ENABLED', true),
        'disk' => (string) env('GENTE_AUDIT_VAULT_DISK', 'local'),
        'path' => (string) env('GENTE_AUDIT_VAULT_PATH', 'secure_vault/audit'),
        'batch_size' => (int) env('GENTE_AUDIT_VAULT_BATCH', 2000),
    ],
    /**
     * Frente 5: conformidade jornada PCCV (Escala de trabalho impositiva + auditoria de exceção).
     */
    'pccv' => [
        'enabled' => (bool) env('GENTE_PCCV_ESCALA_ENABLED', true),
        'semana_iso' => (bool) env('GENTE_PCCV_SEMANA_ISO', true),
        'tolerancia_horas' => (float) env('GENTE_PCCV_TOL_H', 0.25),
        'min_justificativa_chars' => (int) env('GENTE_PCCV_MIN_JUST', 20),
        /** 220h/mês padrão CLT; usado com carga "mensal" no cargo */
        'carga_mensal_referencia' => (int) env('GENTE_PCCV_CARGA_MES_REF', 220),
        'carga_semanal_ref_mes_220' => (int) env('GENTE_PCCV_CARGA_SEM_44', 44),
    ],
    /**
     * RBAC multi-tenant (Fase 3A): matriz YAML, TENANT_TYPE + âncoras GLOBAL_* em UNIDADE real.
     * SECRETARIA: TENANT_ID = UNIDADE_ID da sede executora (mesma regra pragmática do plano).
     * Política de SETOR órfão (pré-NOT NULL): ver migration 2026_05_01_100600_backfill_setor_unidade_id_orphans
     * (âncora por nomes conhecidos ou criação de ADMINISTRAÇÃO CENTRAL).
     */
    'rbac' => [
        'matrix_yaml' => env('GENTE_RBAC_MATRIX_YAML', database_path('rbac/rbac_matrix.v1.yaml')),
        'tenant_types' => ['SECRETARIA', 'UNIDADE', 'POLO', 'GLOBAL_SEMED', 'GLOBAL_SEMAD'],
        'anchor_unidade_nome_global_semed' => (string) env(
            'GENTE_RBAC_ANCORA_SEMED_NOME',
            'GENTE RBAC ancora GLOBAL SEMED'
        ),
        'anchor_unidade_nome_global_semad' => (string) env(
            'GENTE_RBAC_ANCORA_SEMAD_NOME',
            'GENTE RBAC ancora GLOBAL SEMAD'
        ),
        /** Alinhado a database/rbac/rbac_matrix.v1.yaml */
        'perm_slug_override_grade' => (string) env('GENTE_RBAC_PERM_OVERRIDE_GRADE', 'escala.override.sudo_grade'),
        /** Capacidade SEMED na âncora GLOBAL_SEMED — chapéu duplo: UI da manta não trava se existir */
        'perm_slug_edit_grade_semed' => (string) env('GENTE_RBAC_PERM_EDIT_GRADE_SEMED', 'escala.grade.editar'),
        'role_slug_semad_auditor' => (string) env('GENTE_RBAC_ROLE_SEMAD_AUDITOR', 'auditoria_matriz_semad'),
    ],
];
