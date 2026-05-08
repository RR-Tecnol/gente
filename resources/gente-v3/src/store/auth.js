import { defineStore } from 'pinia'
import api from '@/plugins/axios'

/**
 * Autenticação: o login (CPF ou e-mail) é enviado diretamente pelas views para POST /api/auth/login.
 * Este store não altera USUARIO_LOGIN — não há “limpeza” aqui.
 */

const TTL_MS = 5 * 60 * 1000 // 5 minutos

/** Chaves alinhadas ao /api/auth/me (PERFIL_NOME → slug MAIÚSCULO) */
const CHAVE_ADMIN = new Set(['DESENVOLVEDOR', 'ADMINISTRADOR'])
const CHAVE_RH = new Set([
    ...CHAVE_ADMIN,
    'RH_FOLHA',
    'RH_UNIDADE',
    'RH_APS',
    'RH_REDE',
    'RECRUTADOR',
])
const CHAVE_GESTOR = new Set([
    ...CHAVE_RH,
    'GESTAO',
    'COORDENADOR_DE_SETOR',
    'DIRETOR_GESTOR_DE_UNIDADE',
])

function getPerfilChaves(state) {
    const raw = state.user?.perfil_chaves
    if (Array.isArray(raw) && raw.length) {
        return [...new Set(raw.map((c) => String(c).toUpperCase().trim()).filter(Boolean))]
    }
    return []
}

function legacyPerfilLower(state) {
    return (state.user?.perfil ?? '').toLowerCase().trim()
}

/** Alinha com isAdmin: matriz de chaves OU string legada (sessões antigas / fallback) */
function isAdminState(state) {
    const chaves = getPerfilChaves(state)
    if (chaves.some((c) => CHAVE_ADMIN.has(c))) {
        return true
    }
    return ['admin', 'administrador', 'administrator'].includes(legacyPerfilLower(state))
}

function isRHState(state) {
    const chaves = getPerfilChaves(state)
    if (chaves.some((c) => CHAVE_RH.has(c))) {
        return true
    }
    const p = legacyPerfilLower(state)
    return ['admin', 'administrador', 'administrator', 'rh', 'recursos humanos'].includes(p)
}

function isGestorState(state) {
    const chaves = getPerfilChaves(state)
    if (chaves.some((c) => CHAVE_GESTOR.has(c))) {
        return true
    }
    const p = legacyPerfilLower(state)
    return [
        'admin', 'administrador', 'administrator',
        'rh', 'recursos humanos', 'gestor',
    ].includes(p)
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
        _lastFetch: 0, // timestamp da última busca bem-sucedida
        /** Sudo: visão global (cabeçalho X-Gente-Global-View) — só aplica com can_bypass_tenant do /me */
        isGlobalViewActive: false,
        /** Fase 8A — FUNCIONARIO_ID activo para cabeçalho de contexto (acúmulo / multi-matrícula) */
        funcionarioContextId: null,
    }),

    getters: {
        // Perfil retornado pelo backend (string legada — 1º perfil ou “funcionario”)
        perfil: (state) => state.user?.perfil ?? '',

        perfilChaves: (state) => getPerfilChaves(state),

        /** Lista detalhada { id, nome, chave } vinda de /me */
        perfis: (state) => (Array.isArray(state.user?.perfis) ? state.user.perfis : []),

        lotacaoAtiva: (state) => state.user?.lotacao_ativa ?? null,

        /** Lista de vínculos devolvida por GET /api/auth/me */
        funcionarioVinculos: (state) =>
            Array.isArray(state.user?.funcionario_vinculos) ? state.user.funcionario_vinculos : [],

        /** Nome do cabeçalho HTTP (eco do backend) */
        funcionarioContextHeader: (state) =>
            (state.user?.funcionario_context_header && String(state.user.funcionario_context_header).trim())
            || 'X-Gente-Funcionario-Context-Id',

        /** Dois ou mais FUNCIONARIO com o mesmo USUARIO_ID */
        temMultiplosVinculosFuncionario: (state) => {
            const v = state.user?.funcionario_vinculos
            return Array.isArray(v) && v.length > 1
        },

        unidadesEscopo: (state) =>
            Array.isArray(state.user?.unidades_escopo) ? state.user.unidades_escopo : [],

        // Helper interno: normaliza perfil para comparação (legado)
        _perfilNorm: (state) => legacyPerfilLower(state),

        isAdmin: (state) => isAdminState(state),
        isRH: (state) => isRHState(state),
        isGestor: (state) => isGestorState(state),
        isFuncionario: (state) => !!state.user,

        /** /me: Gate bypass-tenant + whitelist .env — se false, o toggle de visão global não aparece */
        canBypassTenant: (state) => !!state.user?.can_bypass_tenant,

        /** RBAC: slugs GENTE_PERMISSION vigentes (Fase 4) */
        rbacPermissionSlugs: (state) => {
            const raw = state.user?.rbac_permission_slugs
            if (!Array.isArray(raw)) return []
            return [...new Set(raw.map((s) => String(s).trim()).filter(Boolean))]
        },

        /** Papel SEMAD auditoria matriz — UI somente leitura nas áreas da Manta */
        semadAuditorReadonly: (state) => !!state.user?.semad_auditor_readonly,

        /**
         * Trava de edição na manta (escala/frequência): false no chapéu duplo TI+SEMED quando /me envia semad_manta_ui_readonly.
         * Fallback legado: igual a semad_auditor_readonly se o campo novo não existir.
         */
        semadMantaUiReadonlyForShell: (state) => {
            const u = state.user
            if (!u) return false
            if (typeof u.semad_manta_ui_readonly === 'boolean') {
                return u.semad_manta_ui_readonly
            }
            return !!u.semad_auditor_readonly
        },

        /** RBAC break-glass (assignment GLOBAL_SEMED + permissão sudo grade) */
        podeRbacBreakGlassGlobal: (state) => !!state.user?.pode_rbac_break_glass_global,

        /** Anéis tenant scope públicos (prefixos API + semad_block_mutations) */
        tenantScopeRingsPublic: (state) =>
            state.user?.tenant_scope_rings_public && typeof state.user.tenant_scope_rings_public === 'object'
                ? state.user.tenant_scope_rings_public
                : {},

        hasRbacSlug: (state) => (slug) => {
            if (slug == null || slug === '') return false
            const alvo = String(slug).trim()
            const raw = state.user?.rbac_permission_slugs
            if (Array.isArray(raw)) {
                for (const s of raw) {
                    if (String(s).trim() === alvo) return true
                }
            }
            return false
        },

        hasAnyRbacSlug: (state) => (slugs) => {
            if (!Array.isArray(slugs) || slugs.length === 0) return true
            const raw = state.user?.rbac_permission_slugs
            if (!Array.isArray(raw) || raw.length === 0) return false
            const set = new Set(raw.map((s) => String(s).trim()).filter(Boolean))
            return slugs.some((s) => set.has(String(s).trim()))
        },

        /** Sessão com /me válido (usar no guard antes de forcePasswordChange) */
        isAuthenticated: (state) => !!state.user,

        /** Troca obrigatória: USUARIO_PRIMEIRO_ACESSO ou USUARIO_ALTERAR_SENHA (API: force_password_change) */
        forcePasswordChange: (state) => {
            const u = state.user
            if (!u) return false
            if (typeof u.force_password_change === 'boolean') return u.force_password_change
            return !!(u.primeiro_acesso) || !!u.alterar_senha
        },

        /**
         * @param {string} chave ex: 'ADMINISTRADOR', 'RH_FOLHA'
         * @returns {boolean}
         */
        hasPerfil: (state) => (chave) => {
            if (chave == null || chave === '') {
                return false
            }
            const alvo = String(chave).toUpperCase().trim()
            const chaves = getPerfilChaves(state)
            if (chaves.length && chaves.includes(alvo)) {
                return true
            }
            // Fallback legado: um único perfil string, sem matriz
            if (!chaves.length && alvo === 'FUNCIONARIO' && legacyPerfilLower(state) === 'funcionario') {
                return true
            }
            return false
        },

        /**
         * Admin (ou desenvolvedor) vê tudo; senão exige unidade em unidades_escopo
         * @param {number|string} unidadeId
         * @returns {boolean}
         */
        hasEscopoUnidade: (state) => (unidadeId) => {
            if (unidadeId === null || unidadeId === undefined || unidadeId === '') {
                return false
            }
            const id = Number(unidadeId)
            if (Number.isNaN(id)) {
                return false
            }
            if (isAdminState(state)) {
                return true
            }
            const escopos = state.user?.unidades_escopo
            if (!Array.isArray(escopos) || escopos.length === 0) {
                return false
            }
            return escopos.some((u) => Number(u?.unidade_id) === id)
        },

        // Nome de exibição do perfil (para sidebar, topbar) — múltiplos perfis no mesmo USUARIO: sem modal de "escolher conta"
        perfilLabel: (state) => {
            const lista = state.user?.perfis
            if (Array.isArray(lista) && lista.length > 1) {
                return lista.map((p) => p.nome).filter(Boolean).join(' + ')
            }
            if (Array.isArray(lista) && lista[0]?.nome) {
                return lista[0].nome
            }
            const p = legacyPerfilLower(state)
            if (['admin', 'administrador', 'administrator'].includes(p)) {
                return 'Administrador'
            }
            if (['rh', 'recursos humanos'].includes(p)) {
                return 'Recursos Humanos'
            }
            if (p === 'gestor') {
                return 'Gestor'
            }
            if (['funcionario', 'funcionário'].includes(p)) {
                return 'Funcionário'
            }
            return state.user?.perfil ?? 'Usuário'
        }
    },

    actions: {
        // BUG-03: cache TTL — só busca /me se passou mais de 5 min ou forceFetch=true
        async fetchUser(forceFetch = false) {
            const now = Date.now()
            if (!forceFetch && this.user && (now - this._lastFetch) < TTL_MS) {
                return // cache ainda válido, não faz request
            }

            this.loading = true
            try {
                const { data } = await api.get('/api/auth/me')
                // Garante que data é um objeto (corrige BOM ou string JSON inesperada)
                if (typeof data === 'string') {
                    const clean = data.replace(/^\uFEFF/, '').trim()
                    this.user = JSON.parse(clean)
                } else {
                    this.user = data
                }
                this._lastFetch = Date.now()
                this._syncSudoFromSession()
                this._syncFuncionarioContextFromUser()
            } catch (error) {
                // Só desautentica em 401 (sessão expirada)
                if (error?.response?.status === 401) {
                    this.user = null
                    this._lastFetch = 0
                    this.isGlobalViewActive = false
                    this.funcionarioContextId = null
                }
            } finally {
                this.loading = false
            }
        },

        // Invalida o cache (chamado após login)
        clearCache() {
            this._lastFetch = 0
        },

        /** Após login ou troca de senha: grava o objeto /me (evita race antes do fetchUser) */
        setSessionUser(payload) {
            this.user = payload
            this._lastFetch = Date.now()
            this._syncSudoFromSession()
            this._syncFuncionarioContextFromUser()
        },

        _funcionarioContextStorageKey() {
            const id = this.user?.id
            if (id == null || id === '') {
                return null
            }
            return `gente_fnctx_${id}`
        },

        /**
         * Restaura ou define o vínculo activo a partir de sessionStorage e da lista permitida em /me.
         */
        _syncFuncionarioContextFromUser() {
            if (!this.user) {
                this.funcionarioContextId = null
                return
            }
            const vinculos = Array.isArray(this.user.funcionario_vinculos) ? this.user.funcionario_vinculos : []
            const permitidos = new Set(
                vinculos.map((v) => Number(v?.id)).filter((n) => Number.isFinite(n) && n > 0)
            )
            const servidor = Number(this.user.funcionario_context_id)
            let escolhido = Number.isFinite(servidor) && servidor > 0 ? servidor : null
            const k = this._funcionarioContextStorageKey()
            if (k && typeof localStorage !== 'undefined') {
                try {
                    const raw = localStorage.getItem(k)
                    const pref = raw != null && raw !== '' ? Number(raw) : NaN
                    if (Number.isFinite(pref) && pref > 0 && permitidos.has(pref)) {
                        escolhido = pref
                    }
                } catch {
                    /* empty */
                }
            }
            if (!escolhido && this.user.funcionario?.id != null) {
                const fid = Number(this.user.funcionario.id)
                if (Number.isFinite(fid) && fid > 0 && (permitidos.size === 0 || permitidos.has(fid))) {
                    escolhido = fid
                }
            }
            if (!escolhido && permitidos.size) {
                escolhido = Math.min(...permitidos)
            }
            this.funcionarioContextId = escolhido
        },

        /**
         * Troca o vínculo activo (re-fetch /me com cabeçalho para lotação_ativa coerente).
         * @param {number|string} funcionarioId
         */
        async setFuncionarioContext(funcionarioId) {
            const fid = Number(funcionarioId)
            if (!Number.isFinite(fid) || fid <= 0) {
                return
            }
            const permitidos = new Set(
                this.funcionarioVinculos.map((v) => Number(v?.id)).filter((n) => Number.isFinite(n) && n > 0)
            )
            if (permitidos.size && !permitidos.has(fid)) {
                return
            }
            this.funcionarioContextId = fid
            const k = this._funcionarioContextStorageKey()
            if (k && typeof localStorage !== 'undefined') {
                try {
                    localStorage.setItem(k, String(fid))
                } catch {
                    /* empty */
                }
            }
            this.clearCache()
            await this.fetchUser(true)
        },

        _sudoSessionKey() {
            const id = this.user?.id
            if (id == null || id === '') {
                return null
            }
            return `gente_sudo_global_view_u${id}`
        },

        /**
         * Restaura o toggle a partir de sessionStorage (por USUARIO_ID) e respeita can_bypass_tenant.
         */
        _syncSudoFromSession() {
            if (!this.user?.can_bypass_tenant) {
                this.isGlobalViewActive = false
                return
            }
            const k = this._sudoSessionKey()
            if (!k || typeof sessionStorage === 'undefined') {
                this.isGlobalViewActive = false
                return
            }
            try {
                this.isGlobalViewActive = sessionStorage.getItem(k) === '1'
            } catch {
                this.isGlobalViewActive = false
            }
        },

        setGlobalViewActive(value) {
            if (!this.user?.can_bypass_tenant) {
                this.isGlobalViewActive = false
                return
            }
            this.isGlobalViewActive = !!value
            const k = this._sudoSessionKey()
            if (!k || typeof sessionStorage === 'undefined') {
                return
            }
            try {
                if (this.isGlobalViewActive) {
                    sessionStorage.setItem(k, '1')
                } else {
                    sessionStorage.removeItem(k)
                }
            } catch {
                /* empty */
            }
        },

        async logout() {
            try {
                await api.post('/api/auth/logout')
            } catch (error) {
                console.error('Logout error:', error)
            } finally {
                this.user = null
                this._lastFetch = 0
                this.isGlobalViewActive = false
                this.funcionarioContextId = null
            }
        }
    }
})
