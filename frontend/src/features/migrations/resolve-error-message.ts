import axios from 'axios'
import type { TFunction } from 'i18next'

/**
 * Maps a failed migrations request onto a localized message (status-only,
 * mirroring `features/imports/use-import.ts`): no server message parsing, so
 * no internal detail (URL, token, exception class) ever reaches the UI.
 */
export function resolveMigrationErrorMessage(error: unknown, t: TFunction): string {
  const status = axios.isAxiosError(error) ? error.response?.status : undefined
  if (status === 403) return t('errors.forbidden')
  if (status === 404) return t('errors.notFound')
  if (status === 422) return t('errors.validation')
  if (status === 502 || status === 504) return t('errors.externalUnavailable')
  return t('errors.generic')
}
