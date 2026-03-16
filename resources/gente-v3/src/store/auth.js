import { defineStore } from 'pinia'
import api from '@/plugins/axios'

const TTL_MS = 5 * 60 * 1000 // 5 minutos

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
        _lastFetch: 0, // timestamp da última busca bem-sucedida
    }),

    getters: {
        // Perfil retornado pelo backend (string)
        perfil: (state) => state.user?.perfil ?? '',

        // Helper interno: normaliza perfil para comparação
        _perfilNorm: (state) => (state.user?.perfil ?? '').toLowerCase().trim(),

        // Helpers semânticos — usado por sidebar, guards e v-if nas views
        isAdmin: (state) => ['admin', 'administrador', 'administrator'].includes((state.user?.perfil ?? '').toLowerCase().trim()),
        isRH: (state) => ['admin', 'administrador', 'administrator', 'rh', 'recursos humanos'].includes((state.user?.perfil ?? '').toLowerCase().trim()),
        isGestor: (state) => ['admin', 'administrador', 'administrator', 'rh', 'recursos humanos', 'gestor'].includes((state.user?.perfil ?? '').toLowerCase().trim()),
        isFuncionario: (state) => !!state.user,

        // Nome de exibição do perfil (para sidebar, topbar)
        perfilLabel: (state) => {
            const p = (state.user?.perfil ?? '').toLowerCase().trim()
            if (['admin', 'administrador', 'administrator'].includes(p)) return 'Administrador'
            if (['rh', 'recursos humanos'].includes(p)) return 'Recursos Humanos'
            if (['gestor'].includes(p)) return 'Gestor'
            if (['funcionario', 'funcionário'].includes(p)) return 'Funcionário'
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
            } catch (error) {
                // Só desautentica em 401 (sessão expirada)
                if (error?.response?.status === 401) {
                    this.user = null
                    this._lastFetch = 0
                }
            } finally {
                this.loading = false
            }
        },

        // Invalida o cache (chamado após login)
        clearCache() {
            this._lastFetch = 0
        },

        async logout() {
            try {
                await api.post('/api/auth/logout')
            } catch (error) {
                console.error('Logout error:', error)
            } finally {
                this.user = null
                this._lastFetch = 0
            }
        }
    }
})

