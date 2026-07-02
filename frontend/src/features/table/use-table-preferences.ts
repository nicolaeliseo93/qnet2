import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { ColumnState } from 'ag-grid-community'
import {
  resetTablePreferences,
  saveTablePreferences,
} from '@/features/table/api'
import { tableKeys } from '@/features/table/use-table-config'
import type { ColumnPreferenceInput } from '@/features/table/types'

/**
 * Translate AG Grid's column state into the preferences payload (0003).
 *
 * Pure and side-effect free so it is unit-testable without a live grid:
 *  - the synthetic row-actions column is excluded (it is not a real column);
 *  - `order` is the column's current display position;
 *  - `visible` is the inverse of AG Grid's `hide`;
 *  - `width` is sent only when it is a real number (omitted, never null, so it
 *    satisfies the backend's integer validation).
 *
 * The backend computes the sparse delta from this full state, so the frontend
 * does not need to know the defaults.
 */
export function toColumnPreferences(
  state: ColumnState[],
  actionsColumnId: string,
): ColumnPreferenceInput[] {
  return state
    .filter((column) => column.colId !== actionsColumnId)
    .map((column, index) => {
      const preference: ColumnPreferenceInput = {
        id: column.colId,
        visible: !column.hide,
        order: index,
      }

      if (typeof column.width === 'number') {
        preference.width = column.width
      }

      return preference
    })
}

/**
 * Upserts the user's column layout for a domain. On success the returned merged
 * config refreshes the cache, so a remount within the config staleTime restores
 * the just-saved layout rather than the stale default.
 */
export function useSaveTablePreferences(domain: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (columns: ColumnPreferenceInput[]) =>
      saveTablePreferences(domain, columns),
    onSuccess: (config) => {
      queryClient.setQueryData(tableKeys.config(domain), config)
    },
  })
}

/**
 * Resets the user's column layout for a domain to the PHP default. The caller
 * refetches the config and remounts the grid so the defaults take effect.
 */
export function useResetTablePreferences(domain: string) {
  return useMutation({
    mutationFn: () => resetTablePreferences(domain),
  })
}
