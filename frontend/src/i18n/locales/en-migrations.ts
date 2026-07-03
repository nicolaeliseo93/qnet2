/**
 * Localized strings for the external data migrations module (spec 0013):
 * the super-admin-only preview (fase 1) page and the import (fase 2)
 * confirm/progress/summary dialog.
 *
 * Extracted into its own module to keep `en.ts` within the engineering size
 * limits (see `.claude/rules/engineering.md` §6). Public API of `en.ts` is
 * unchanged.
 */
export const migrations = {
  nav: {
    label: 'Migrations',
  },
  page: {
    import: 'Import',
    sourceLabel: 'Source',
    sourcePlaceholder: 'Select a source…',
    sourcesLoadError: 'Unable to load the migration sources. Please try again.',
    sourcesEmpty: 'No migration sources are registered.',
    pageIndicator: 'Page {{page}}',
    previous: 'Previous',
    next: 'Next',
  },
  preview: {
    loadError: 'Unable to load the preview. Please try again.',
    empty: 'No records found for this source.',
  },
  import: {
    title: 'Import {{source}}',
    subtitle: 'Review starts the background import; re-running it is safe (idempotent).',
    confirmDescription:
      'This creates the records in qnet from the external source, in the background. Records already imported are skipped, so running it again is safe.',
    start: 'Start import',
    starting: 'Starting…',
    close: 'Close',
    summary: {
      total: 'Total rows',
      created: 'Created',
      skipped: 'Skipped',
      failed: 'Failed',
    },
    reportTitle: 'Warnings and errors',
    reportLevel: {
      warning: 'Warning',
      error: 'Error',
    },
  },
  status: {
    pending: 'Pending',
    processing: 'Importing',
    completed: 'Completed',
    failed: 'Failed',
  },
  errors: {
    forbidden: "You don't have permission to access migrations.",
    notFound: 'This migration source does not exist.',
    validation: 'Invalid pagination parameters.',
    externalUnavailable: 'The external system is currently unavailable. Please try again.',
    generic: 'Something went wrong. Please try again.',
    jobFailed: 'The import failed. Please try again.',
  },
}
