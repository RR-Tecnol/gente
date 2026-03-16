import axios from 'axios'
import * as SecureStore from 'expo-secure-store'

// ── Altere para a URL do seu servidor Laravel ──────────────────────
const BASE_URL = 'http://192.168.1.100:8000/api/v3'
// Em produção: 'https://sistema.exemplo.gov.br/api/v3'

const api = axios.create({
    baseURL: BASE_URL,
    timeout: 10000,
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
})

// Injeta token JWT em todas as requisições
api.interceptors.request.use(async (config) => {
    const token = await SecureStore.getItemAsync('jwt_token')
    if (token) config.headers.Authorization = `Bearer ${token}`
    return config
})

export default api
