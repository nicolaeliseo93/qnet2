import { useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { moduleStatsQueryKey } from '@/features/stats/api'

/**
 * Stable callback that marks a module's statistics stale after any mutation
 * that changes its data (create/update/delete/import). Every table adapter
 * calls this once, alongside its existing grid refresh, instead of repeating
 * the invalidation logic (spec 0026).
 *
 * `invalidateQueries` never issues a request by itself: with the panel closed
 * (its query unmounted) the data is just marked stale and served fresh on the
 * next open (AC-007 stays satisfied); with the panel open (an active query)
 * TanStack Query refetches immediately, so the KPIs update without the user
 * closing and reopening the panel.
 */
export function useInvalidateModuleStats(domain: string): () => void {
  const queryClient = useQueryClient()

  return useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: moduleStatsQueryKey(domain) })
  }, [queryClient, domain])
}
