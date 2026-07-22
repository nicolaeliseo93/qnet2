/**
 * Impersonation banner and row-action strings (spec 0050). Split out of
 * `it.ts` to stay within the engineering size limits (`.claude/rules/
 * engineering.md` §6).
 */

export const impersonation = {
  operatingAs: 'Stai operando come {{name}}',
  exit: 'Torna al tuo account',
  startError: "Impossibile avviare l'impersonificazione. Riprova.",
  stopError: 'Impossibile tornare al tuo account. Riprova.',
}
