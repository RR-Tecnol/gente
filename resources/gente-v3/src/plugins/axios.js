import axios from 'axios'

const api = axios.create({
    baseURL: '/', // O proxy do vite vai rotear para o 127.0.0.1:8000
    withCredentials: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest', // Essencial para o Laravel reconhecer requests AJAX
    }
})

// Interceptador para tratar sessão expirada
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response && [401, 419].includes(error.response.status)) {
            // Só redireciona se não estamos já na tela de login
            if (!window.location.pathname.includes('/login')) {
                window.location.href = '/login'
            }
        }
        return Promise.reject(error)
    }
)

export default api
