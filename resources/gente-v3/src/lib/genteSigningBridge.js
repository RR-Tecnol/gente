/**
 * Evita dependência circular axios ↔ store/auth.
 * main.js chama setGenteSigningUserGetter após pinia.
 */
let getUser = () => null

export function setGenteSigningUserGetter(fn) {
  getUser = typeof fn === 'function' ? fn : () => null
}

export function getSigningUser() {
  return getUser()
}
