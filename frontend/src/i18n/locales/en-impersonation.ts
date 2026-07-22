/**
 * Impersonation banner and row-action strings (spec 0050). Split out of
 * `en.ts` to stay within the engineering size limits (`.claude/rules/
 * engineering.md` §6).
 */

export const impersonation = {
  operatingAs: 'You are operating as {{name}}',
  exit: 'Back to your account',
  startError: 'Unable to start impersonation. Please try again.',
  stopError: 'Unable to return to your account. Please try again.',
}
