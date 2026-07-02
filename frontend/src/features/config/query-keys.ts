/**
 * Query keys for the public application config. A single static entry: the
 * payload is process-wide presentation metadata, fetched once and cached.
 */
export const configKeys = {
  all: ['config'] as const,
}
