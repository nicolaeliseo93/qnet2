import { useCallback } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import axios from 'axios'
import { toast } from 'sonner'
import type { CellValueChangedEvent } from 'ag-grid-community'
import type { ApiErrorResponse } from '@/api/types'
import { updateTableCell } from '@/features/table/api'
import type { TableRow } from '@/features/table/types'

/** Resolves the toast message for a failed cell PATCH: the server's own message (D-9), a generic fallback otherwise. */
function resolveCellUpdateErrorMessage(error: unknown, t: TFunction): string {
  if (axios.isAxiosError<ApiErrorResponse>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return t('table.cellUpdateError')
}

/**
 * Wires AG Grid's `onCellValueChanged` to the generic per-cell PATCH endpoint
 * (spec 0053): guards a no-op edit, swaps the row for the server's re-mapped
 * copy on success (`node.setData`), and reverts to the previous value with a
 * toast of the server's message on failure. Mirrors the import wizard's
 * review grid (`features/imports/wizard/use-review-rows.ts`), the only prior
 * cell-edit -> PATCH -> setData/revert cycle in the repo — that engine stays
 * untouched, this is the generic table's own instance of the same pattern.
 */
export function useTableCellEdit(domain: string) {
  const { t } = useTranslation()

  const updateCellMutation = useMutation({
    mutationFn: ({ rowId, column, value }: { rowId: number; column: string; value: string | number | boolean | null }) =>
      updateTableCell(domain, rowId, { column, value }),
  })

  // Step 1: ignore edits with no side effect — an unauthorized/unregistered
  // column never wires an editor at all, and a same-value commit (Esc, or
  // Enter without a change) must not fire a network call (AC-021).
  // Step 2: PATCH the edited field; on success replace the row with the
  // server's re-mapped copy so derived columns/`updated_at` stay coherent
  // without a full SSRM refresh (AC-020). On failure, put the pre-edit value
  // back and toast the server's message (AC-019).
  const handleCellValueChanged = useCallback(
    (event: CellValueChangedEvent<TableRow>) => {
      if (!event.data || event.newValue === event.oldValue) {
        return
      }

      const rowId = event.data.id
      const columnId = event.column.getColId()
      const revertedData: TableRow = { ...event.data, [columnId]: event.oldValue }

      updateCellMutation.mutate(
        { rowId, column: columnId, value: event.newValue as string | number | boolean | null },
        {
          onSuccess: (row) => {
            event.node.setData(row)
          },
          onError: (error) => {
            event.node.setData(revertedData)
            toast.error(resolveCellUpdateErrorMessage(error, t))
          },
        },
      )
    },
    [t, updateCellMutation],
  )

  return { handleCellValueChanged }
}
