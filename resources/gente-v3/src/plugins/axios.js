import axios from 'axios'
import { getSigningUser } from '@/lib/genteSigningBridge'
import { getGenteSudoGlobalState } from '@/lib/genteSudoGlobalBridge'
import { getGenteFuncionarioContextState } from '@/lib/genteFuncionarioContextBridge'
import { buildFullPathFromAxiosConfig, genteHmacSignatureHex, serializarCorpoRequisicao } from '@/lib/genteRequestSign'

const api = axios.create({
    baseURL: '/', // O proxy do vite vai rotear para o 127.0.0.1:8000
    withCredentials: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest', // Essencial para o Laravel reconhecer requests AJAX
    },
    // Remove BOM (\uFEFF) antes do JSON.parse — PHP pode emitir múltiplos BOMs
    transformResponse: [function (data) {
        if (typeof data === 'string') {
            const clean = data.replace(/^\uFEFF+/, '')
            try { return JSON.parse(clean) } catch { return clean }
        }
        return data
    }],
})

// Fase 8A — vínculo activo (acúmulo): âncora opcional para /api/auth/me e APIs tenant
api.interceptors.request.use(
    (config) => {
        try {
            const c = getGenteFuncionarioContextState()
            const hid = c?.funcionarioContextId
            const hname = c?.headerName
            if (hname && hid != null && Number(hid) > 0) {
                config.headers = config.headers || {}
                config.headers[hname] = String(Number(hid))
            }
        } catch {
            /* store ainda não montado */
        }
        return config
    },
    (e) => Promise.reject(e)
)

// Sudo / visão global: cabeçalho explícito em todos os verbos (GET incluído) — alinhado ao backend GenteSudoGlobalView
api.interceptors.request.use(
    (config) => {
        try {
            const s = getGenteSudoGlobalState()
            if (s?.canBypassTenant && s?.isGlobalViewActive && s?.headerName) {
                config.headers = config.headers || {}
                config.headers[s.headerName] = 'true'
            }
        } catch {
            /* store ainda não montado */
        }
        return config
    },
    (e) => Promise.reject(e)
)

// Frente 2: integridade de payload (HMAC + timestamp anti-replay)
api.interceptors.request.use(
    (config) => {
        const m = String(config.method || 'get').toLowerCase()
        if (!['post', 'put', 'patch', 'delete'].includes(m)) {
            return config
        }
        try {
            const u = getSigningUser()
            if (!u?.request_signing_enabled || !u?.request_signing_secret) {
                return config
            }
            const path = buildFullPathFromAxiosConfig(config)
            const raw = serializarCorpoRequisicao(config)
            const ts = String(Date.now())
            const secret = String(u.request_signing_secret)
            const sig = genteHmacSignatureHex(m, path, ts, raw, secret)
            config.headers = config.headers || {}
            config.headers['X-Gente-Timestamp'] = ts
            config.headers['X-Gente-Signature'] = sig
        } catch {
            // store não inicializado: pedido segue sem assinatura; backend pode devolver 403 se exigir
        }
        return config
    },
    (e) => Promise.reject(e)
)

// Interceptador para tratar sessão expirada (SEC-PROD-06)
api.interceptors.response.use(
    response => response,
    error => {
        const status = error?.response?.status
        const payloadError = error?.response?.data?.error

        // Primeiro acesso / troca obrigatória: backend bloqueia com 412 (ou 403 em ambientes legados)
        if ((status === 412 || status === 403) && payloadError === 'PASSWORD_CHANGE_REQUIRED') {
            import('@/store/auth').then(({ useAuthStore }) => {
                try { useAuthStore().clearCache() } catch (e) { /* empty */ }
            }).catch(() => { })
            if (!window.location.pathname.includes('/primeiro-acesso')) {
                window.location.href = '/primeiro-acesso'
            }
            return Promise.reject(error)
        }

        if (error.response && [401, 419].includes(error.response.status)) {
            import('@/store/auth').then(({ useAuthStore }) => {
                try { useAuthStore().clearCache() } catch (e) { /* empty */ }
            }).catch(() => { })

            // Só redireciona se não estamos já na tela de login
            // Usa router push em vez de location.href para evitar reload do SPA
            if (!window.location.pathname.includes('/login')) {
                import('@/router').then(({ default: router }) => {
                    router.push({ name: 'Login', query: { sessao_expirada: '1' } })
                }).catch(() => {
                    // Fallback se import dinâmico falhar
                    window.location.href = '/login?sessao_expirada=1'
                })
            }
        }
        return Promise.reject(error)
    }
)

export default api
