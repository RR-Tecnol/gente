/**
 * Evita dependência circular axios ↔ store/auth (igual a genteSigningBridge).
 * main.js chama setGenteSudoGlobalGetter após pinia.
 */
let getSudo = () => ({
  canBypassTenant: false,
  isGlobalViewActive: false,
  headerName: 'X-Gente-Global-View',
})

export function setGenteSudoGlobalGetter(fn) {
  getSudo = typeof fn === 'function' ? fn : () => ({
    canBypassTenant: false,
    isGlobalViewActive: false,
    headerName: 'X-Gente-Global-View',
  })
}

export function getGenteSudoGlobalState() {
  return getSudo()
}
