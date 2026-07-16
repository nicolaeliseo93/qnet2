import type { ImportRunStatus } from '@/features/imports/wizard/types'

/**
 * A concluded run (spec 0034 AC-012/AC-013) is shown read-only in the
 * dedicated detail page; every other status can still resume the wizard.
 * Kept as the complement of the two terminal statuses rather than an
 * enumeration of the five in-progress ones, so a future status added to
 * `ImportRunStatus` defaults to "resumable" instead of silently falling out
 * of both sets.
 */
const CONCLUDED_STATUSES: ReadonlySet<ImportRunStatus> = new Set(['completed', 'failed'])

/** A run whose wizard flow has finished (successfully or not). */
export function isConcludedImportRun(status: string): boolean {
  return CONCLUDED_STATUSES.has(status as ImportRunStatus)
}

/** A run that can still be resumed in the import wizard. */
export function isResumableImportRun(status: string): boolean {
  return !isConcludedImportRun(status)
}
