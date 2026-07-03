/**
 * Query keys for the generic imports module (spec 0012). Scoped per domain and
 * per run id so polling a given run never collides with another domain/run.
 */
export const importKeys = {
  all: ['imports'] as const,
  domain: (domain: string) => ['imports', domain] as const,
  run: (domain: string, importRunId: number) => ['imports', domain, importRunId] as const,
}
