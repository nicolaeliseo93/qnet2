import type { ImportRunRowsQuery } from '@/features/imports/wizard/types'

/**
 * Query keys for the advanced import wizard (spec 0033), scoped per domain
 * and run id so polling a given run never collides with another domain/run,
 * and namespaced under `imports/wizard` so it never collides with the
 * legacy two-phase import keys (`features/imports/query-keys.ts`).
 */
export const importWizardKeys = {
  all: ['imports', 'wizard'] as const,
  domain: (domain: string) => ['imports', 'wizard', domain] as const,
  run: (domain: string, importRunId: number) => ['imports', 'wizard', domain, importRunId] as const,
  /** Owned by F2 (review grid): kept centralized here so the key shape is defined once. */
  rows: (domain: string, importRunId: number, query: ImportRunRowsQuery) =>
    ['imports', 'wizard', domain, importRunId, 'rows', query] as const,
  /** Owned by F3 (summary step). */
  summary: (domain: string, importRunId: number) =>
    ['imports', 'wizard', domain, importRunId, 'summary'] as const,
}
