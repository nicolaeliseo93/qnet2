import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  resetTableFilters,
  saveTableFilters,
  type SaveTableFiltersPayload,
} from '@/features/table/api'
import { tableKeys } from '@/features/table/use-table-config'
import type { TableConfig } from '@/features/table/types'

/**
 * Upserts the user's applied filterModel and/or advanced filters (spec 0032)
 * for a domain so they survive a reload; each key persists independently. On
 * success the returned merged config refreshes the cache, so a remount within
 * the config staleTime restores the just-saved state rather than the stale
 * default.
 */
export function useSaveTableFilters(domain: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: SaveTableFiltersPayload) => saveTableFilters(domain, payload),
    onSuccess: (config: TableConfig) => {
      queryClient.setQueryData(tableKeys.config(domain), config)
    },
  })
}

/**
 * Resets the user's saved filters for a domain. The caller refetches the config
 * and remounts the grid so the filters clear and the SSRM re-queries unfiltered.
 */
export function useResetTableFilters(domain: string) {
  return useMutation({
    mutationFn: () => resetTableFilters(domain),
  })
}
