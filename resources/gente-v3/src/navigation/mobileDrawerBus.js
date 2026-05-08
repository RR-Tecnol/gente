/** Fecha o drawer mobile após navegação (router.afterEach + layout registra callback). */
let closeDrawerFn = null

export function registerMobileDrawerClose(fn) {
  closeDrawerFn = typeof fn === 'function' ? fn : null
}

export function notifyMobileDrawerClose() {
  try {
    closeDrawerFn?.()
  } catch {
    /* empty */
  }
}
