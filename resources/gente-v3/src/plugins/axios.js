import axios from 'axios'
import { useAuthStore } from '@/store/auth'

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

// Interceptador para tratar sessão expirada (SEC-PROD-06)
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response && [401, 419].includes(error.response.status)) {
            try {
                const authStore = useAuthStore()
                authStore.clearCache()
            } catch (e) {
                // store might not be ready but it's fine
            }
            
            // Só redireciona se não estamos já na tela de login
            if (!window.location.pathname.includes('/login')) {
                window.location.href = '/login?sessao_expirada=1'
            }
        }
        return Promise.reject(error)
    }
)

export default api
