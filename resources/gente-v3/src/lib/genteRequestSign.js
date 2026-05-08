/**
 * Frente 2 — LACRE DIGITAL (HMAC)
 * O servidor recompõe: HMAC-SHA256( MÉTODO + "\n" + path + "\n" + ts_ms + "\n" + corpo_raw , segredo_sessão )
 * Corpo: string exatamente como enviada (para JSON, use JSON.stringify do mesmo objeto).
 */
import CryptoJS from 'crypto-js'

/**
 * @param {string} method
 * @param {string} path  ex: "/api/v3/escala-trabalho" (inclui prefixo /api se for esse o URL do axios)
 * @param {string} timestampMs
 * @param {string} rawBody
 * @param {string} secret
 * @returns {string} hex minúsculo
 */
export function genteHmacSignatureHex(method, path, timestampMs, rawBody, secret) {
  const m = String(method || 'GET').toUpperCase()
  const p = path && path.length ? (path.startsWith('/') ? path : `/${path}`) : '/'
  const msg = `${m}\n${p}\n${timestampMs}\n${rawBody ?? ''}`
  return CryptoJS.HmacSHA256(msg, secret).toString(CryptoJS.enc.Hex)
}

/**
 * Junta baseURL (ex: "/") e url (ex: "api/v3/foo") no caminho de pedido.
 */
export function buildFullPathFromAxiosConfig(config) {
  const b = (config.baseURL != null ? String(config.baseURL) : '').replace(/\/$/, '')
  const u = (config.url != null ? String(config.url) : '').split('?')[0].replace(/^\//, '')
  if (u.startsWith('http')) {
    try {
      return new URL(u).pathname || '/'
    } catch {
      return '/'
    }
  }
  const j = [b, u].filter(Boolean).join('/').replace(/\/+/g, '/')
  if (!j.startsWith('/')) return `/${j}`
  return j || '/'
}

/**
 * Converte o body que o axios vai enviar em string (alinhado ao getContent() do Laravel).
 * @param {import('axios').InternalAxiosRequestConfig} config
 */
export function serializarCorpoRequisicao(config) {
  const d = config.data
  if (d === undefined || d === null) return ''
  if (typeof d === 'string') return d
  if (typeof FormData !== 'undefined' && d instanceof FormData) {
    return ''
  }
  if (typeof d === 'object' && d.buffer instanceof ArrayBuffer) {
    return ''
  }
  try {
    return JSON.stringify(d)
  } catch {
    return String(d)
  }
}
