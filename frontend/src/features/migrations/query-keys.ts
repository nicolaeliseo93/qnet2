/**
 * Query keys for the external data migrations module (spec 0013). Scoped per
 * source and per run id so polling a given run never collides with another
 * source/run, mirroring `features/imports/query-keys.ts`.
 */
export const migrationKeys = {
  sources: ['migrations', 'sources'] as const,
  columns: (source: string) => ['migrations', source, 'columns'] as const,
  preview: (source: string, page: number, perPage: number) =>
    ['migrations', source, 'preview', page, perPage] as const,
  run: (source: string, runId: number) => ['migrations', source, 'runs', runId] as const,
  /** Stable placeholder key used while no run has started yet (query disabled). */
  idleRun: (source: string) => ['migrations', source, 'runs', 'idle'] as const,
}
