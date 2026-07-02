/**
 * Single source of truth for the Bearer access token.
 *
 * The token is persisted in localStorage so the session survives reloads.
 * Trade-off: localStorage is readable by JavaScript, so it is exposed to XSS.
 * This is acceptable for a stateless Bearer-token API and is mitigated by a
 * strict CSP and never rendering unsanitized HTML. Switching to httpOnly
 * cookies would require backend (Sanctum stateful) changes.
 */
const TOKEN_KEY = 'auth.token'

export const tokenStorage = {
  get(): string | null {
    return localStorage.getItem(TOKEN_KEY)
  },
  set(token: string): void {
    localStorage.setItem(TOKEN_KEY, token)
  },
  clear(): void {
    localStorage.removeItem(TOKEN_KEY)
  },
}
