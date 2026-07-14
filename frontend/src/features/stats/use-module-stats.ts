import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchModuleStats, moduleStatsQueryKey } from '@/features/stats/api'
import type { ModuleStats } from '@/features/stats/types'

/** Aggregate queries are not free: keep them fresh for a minute (spec 0026 D-2). */
const STATS_STALE_TIME_MS = 60_000

/**
 * Server state of a module's statistics panel. `enabled` mirrors the panel's
 * open state (D-2 / AC-007): no request is ever issued while the panel is
 * closed, and the first open triggers exactly one fetch.
 */
export function useModuleStats(domain: string, enabled = true) {
  return useQuery<ModuleStats, AxiosError>({
    queryKey: moduleStatsQueryKey(domain),
    queryFn: () => fetchModuleStats(domain),
    enabled,
    staleTime: STATS_STALE_TIME_MS,
  })
}
