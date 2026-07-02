import { fetchConfig } from '@/features/config/api'
import { configKeys } from '@/features/config/query-keys'

/**
 * Shared query options for the public application config, consumed by BOTH the
 * boot prefetch (main.tsx → `ensureQueryData`) and `useConfig`. Keeping them in
 * one place guarantees the prefetch and the hook use the exact same query key,
 * fetcher, cache lifetime AND retry policy — so the first request truly dedupes
 * (no double fetch) and the bootstrap dependency is equally resilient whether it
 * is first emitted by the prefetch or by the hook.
 *
 * Config is the bootstrap dependency, so it overrides the global retry:1 with a
 * few attempts and exponential backoff (1s, 2s, 4s, capped at 30s) so a
 * transient hiccup doesn't block app boot.
 */
export const configQueryOptions = {
  queryKey: configKeys.all,
  queryFn: fetchConfig,
  staleTime: Infinity,
  retry: 3,
  retryDelay: (attemptIndex: number) => Math.min(1000 * 2 ** attemptIndex, 30_000),
} as const
