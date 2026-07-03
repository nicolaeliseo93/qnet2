/**
 * Query keys for the generic exports module (spec 0014). Scoped per domain and
 * per run id so polling a given run never collides with another domain/run.
 */
export const exportKeys = {
  all: ['exports'] as const,
  domain: (domain: string) => ['exports', domain] as const,
  run: (domain: string, exportRunId: number) => ['exports', domain, exportRunId] as const,
}
