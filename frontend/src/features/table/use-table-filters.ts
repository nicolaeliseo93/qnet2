import { useMutation, useQueryClient } from '@tanstack/react-query'
import { resetTableFilters, saveTableFilters } from '@/features/table/api'
import { tableKeys } from '@/features/table/use-table-config'
import type { TableConfig } from '@/features/table/types'

/**
 * Upserts the user's applied filterModel for a domain so filters survive a
 * reload. On success the returned merged config refreshes the cache, so a
 * remount within the config staleTime restores the just-saved filters rather
 * than the stale (unfiltered) default.
 */
export function useSaveTableFilters(domain: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (filterModel: Record<string, unknown>) =>
      saveTableFilters(domain, filterModel),
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
