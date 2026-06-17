/**
 * Evita dependência circular axios ↔ store/auth (igual a genteSudoGlobalBridge).
 * main.js regista o getter após pinia.
 */
let getCtx = () => ({
  headerName: null,
  funcionarioContextId: null,
})

export function setGenteFuncionarioContextGetter(fn) {
  getCtx = typeof fn === 'function' ? fn : () => ({
    headerName: null,
    funcionarioContextId: null,
  })
}

export function getGenteFuncionarioContextState() {
  return getCtx()
}
