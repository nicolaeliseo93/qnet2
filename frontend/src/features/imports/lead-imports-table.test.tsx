import { beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { render, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { LeadImportsTable } from '@/features/imports/lead-imports-table'
import { moduleStatsQueryKey } from '@/features/stats/api'

/**
 * Spec 0034 AC-012: the generic `<TableView>` (AG Grid + SSRM) is a framework
 * piece outside this adapter's ownership: it is stubbed to capture the
 * `onAction` handler and expose a `refresh` spy. The suite asserts only what
 * THIS adapter owns — the domain rename to `import-runs`, routing `view` by
 * run status (dedicated detail page for a concluded run, wizard resume
 * otherwise), and the generic bulk-delete + stats invalidation on `delete`.
 */
const navigateMock = vi.fn()
vi.mock('react-router-dom', () => ({
  useNavigate: () => navigateMock,
}))

const bulkDeleteMock = vi.fn()
vi.mock('@/features/table/api', () => ({
  bulkDeleteTableRows: (domain: string, ids: number[]) => bulkDeleteMock(domain, ids),
}))

const toastSuccess = vi.fn()
const toastError = vi.fn()
vi.mock('sonner', () => ({
  toast: { success: (m: string) => toastSuccess(m), error: (m: string) => toastError(m) },
}))

let capturedOnAction: RowActionHandler | undefined
const refreshSpy = vi.fn()

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshSpy }))
      capturedOnAction = onAction
      return <div role="region" aria-label={`table-${domain}`} />
    },
  ),
}))

const viewAction = { key: 'view' } as TableActionDefinition
const deleteAction = { key: 'delete' } as TableActionDefinition
const concludedRow = { id: 42, actions: ['view', 'delete'], status: 'completed' } as unknown as TableRow
const resumableRow = { id: 7, actions: ['view', 'delete'], status: 'reviewing' } as unknown as TableRow

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return { ...render(
    <QueryClientProvider client={client}>
      <LeadImportsTable />
    </QueryClientProvider>,
  ), client }
}

describe('LeadImportsTable', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    capturedOnAction = undefined
  })

  it('mounts the generic table for the import-runs domain', () => {
    const { getByLabelText } = renderTable()
    expect(getByLabelText('table-import-runs')).toBeInTheDocument()
  })

  it('navigates to the detail page for a concluded run (completed/failed)', () => {
    renderTable()
    capturedOnAction?.(viewAction, concludedRow)
    expect(navigateMock).toHaveBeenCalledWith('/imports/42')
  })

  it('navigates to the wizard for a resumable run', () => {
    renderTable()
    capturedOnAction?.(viewAction, resumableRow)
    expect(navigateMock).toHaveBeenCalledWith('/imports/new?runId=7')
  })

  it('deletes through the generic bulk-delete endpoint, refreshes and invalidates stats', async () => {
    bulkDeleteMock.mockResolvedValue({ deleted: 1, failed: [] })
    const { client } = renderTable()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    capturedOnAction?.(deleteAction, concludedRow)

    await waitFor(() => expect(bulkDeleteMock).toHaveBeenCalledWith('import-runs', [42]))
    await waitFor(() => expect(refreshSpy).toHaveBeenCalled())
    expect(toastSuccess).toHaveBeenCalled()
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: moduleStatsQueryKey('import-runs') })
  })

  it('does not refresh or invalidate stats when nothing was deleted', async () => {
    bulkDeleteMock.mockResolvedValue({ deleted: 0, failed: [{ id: 42, reason: 'forbidden' }] })
    const { client } = renderTable()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    capturedOnAction?.(deleteAction, concludedRow)

    await waitFor(() => expect(bulkDeleteMock).toHaveBeenCalled())
    expect(refreshSpy).not.toHaveBeenCalled()
    expect(toastError).toHaveBeenCalled()
    expect(invalidateSpy).not.toHaveBeenCalled()
  })
})
