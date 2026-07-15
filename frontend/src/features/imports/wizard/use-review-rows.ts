import { useCallback, useMemo } from 'react'
import { useMutation } from '@tanstack/react-query'
import type {
  CellValueChangedEvent,
  IServerSideDatasource,
  IServerSideGetRowsParams,
} from 'ag-grid-community'
import { getImportRunRows, updateImportRunRow } from '@/features/imports/wizard/api'
import { reviewValueKeyOf } from '@/features/imports/wizard/review-columns'
import type { ImportRunRowCounts, ImportRunRowItem } from '@/features/imports/wizard/types'

/** Page size fallback when the grid does not provide an explicit block range. */
const DEFAULT_BLOCK_SIZE = 25

interface UseReviewRowsArgs {
  domain: string
  importRunId: number
  /** Called after a successful inline edit, with the server-authoritative row and run counts. */
  onRowUpdated: (row: ImportRunRowItem, counts: ImportRunRowCounts) => void
}

/**
 * Owns the review grid's two server interactions (AC-023): the SSRM
 * datasource reading staged rows (`POST .../rows`), and the inline-edit
 * mutation (`PATCH .../rows/{row}`) wired to AG Grid's `onCellValueChanged`.
 * Rows themselves are NOT cached in TanStack Query — AG Grid owns their
 * lifecycle (its SSRM cache); a successful edit refreshes only the edited
 * row's node in place (`rowNode.setData`), a failed one reverts it the same
 * way, so the rest of the loaded block/scroll position is undisturbed.
 */
export function useReviewRows({ domain, importRunId, onRowUpdated }: UseReviewRowsArgs) {
  const updateRowMutation = useMutation({
    mutationFn: ({ rowId, values }: { rowId: number; values: Record<string, string> }) =>
      updateImportRunRow(domain, importRunId, rowId, values),
  })

  const datasource = useMemo<IServerSideDatasource<ImportRunRowItem>>(
    () => ({
      async getRows(params: IServerSideGetRowsParams<ImportRunRowItem>): Promise<void> {
        const { request } = params
        const startRow = request.startRow ?? 0
        const endRow = request.endRow ?? startRow + DEFAULT_BLOCK_SIZE
        try {
          const page = await getImportRunRows(domain, importRunId, {
            startRow,
            endRow,
            sortModel: request.sortModel.map((item) => ({ colId: item.colId, sort: item.sort })),
            filterModel:
              request.filterModel && !Array.isArray(request.filterModel)
                ? (request.filterModel as Record<string, unknown>)
                : {},
          })
          params.success({ rowData: page.items, rowCount: page.pagination.total })
        } catch {
          params.fail()
        }
      },
    }),
    [domain, importRunId],
  )

  // Step 1: resolve which `values` key the edited column writes to (null for
  // a read-only column, which never reaches here since none are editable).
  // Step 2: PATCH just that field; on success replace the row with the
  // server's re-validated copy (status/messages/is_edited may all have
  // changed) and bubble the recalculated run counts. On failure, put the
  // pre-edit value back so the grid never shows an unsaved, silently-lost edit.
  const handleCellValueChanged = useCallback(
    (event: CellValueChangedEvent<ImportRunRowItem>) => {
      const valueKey = reviewValueKeyOf(event.column.getColId())
      if (!valueKey || !event.data || event.newValue === event.oldValue) {
        return
      }

      const rowId = event.data.id
      const revertedData: ImportRunRowItem = {
        ...event.data,
        values: { ...event.data.values, [valueKey]: event.oldValue ?? '' },
      }

      updateRowMutation.mutate(
        { rowId, values: { [valueKey]: String(event.newValue ?? '') } },
        {
          onSuccess: (result) => {
            event.node.setData(result.row)
            onRowUpdated(result.row, result.counts)
          },
          onError: () => {
            event.node.setData(revertedData)
          },
        },
      )
    },
    [onRowUpdated, updateRowMutation],
  )

  return {
    datasource,
    handleCellValueChanged,
    isSaving: updateRowMutation.isPending,
    hasSaveError: updateRowMutation.isError,
  }
}
