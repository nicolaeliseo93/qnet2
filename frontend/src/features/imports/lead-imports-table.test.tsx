import { beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { render, waitFor } from '@testing-library/react'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { LeadImportsTable } from '@/features/imports/lead-imports-table'

/**
 * The generic `<TableView>` (AG Grid + SSRM) is a framework piece outside this
 * adapter's ownership: it is stubbed to capture the `onAction` handler and
 * expose a `refresh` spy. The suite asserts only what THIS adapter owns —
 * mapping the `view` action to a wizard navigation and the `delete` action to
 * the generic bulk-delete endpoint followed by a grid refresh.
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
const row = { id: 42, actions: ['view', 'delete'] } as unknown as TableRow

describe('LeadImportsTable', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    capturedOnAction = undefined
  })

  it('mounts the generic table for the lead-imports domain', () => {
    const { getByLabelText } = render(<LeadImportsTable />)
    expect(getByLabelText('table-lead-imports')).toBeInTheDocument()
  })

  it('navigates to the wizard for the view action', () => {
    render(<LeadImportsTable />)
    capturedOnAction?.(viewAction, row)
    expect(navigateMock).toHaveBeenCalledWith('/leads/import?runId=42')
  })

  it('deletes through the generic bulk-delete endpoint and refreshes on the delete action', async () => {
    bulkDeleteMock.mockResolvedValue({ deleted: 1, failed: [] })
    render(<LeadImportsTable />)

    capturedOnAction?.(deleteAction, row)

    await waitFor(() => expect(bulkDeleteMock).toHaveBeenCalledWith('lead-imports', [42]))
    await waitFor(() => expect(refreshSpy).toHaveBeenCalled())
    expect(toastSuccess).toHaveBeenCalled()
  })

  it('does not refresh and surfaces an error when nothing was deleted', async () => {
    bulkDeleteMock.mockResolvedValue({ deleted: 0, failed: [{ id: 42, reason: 'forbidden' }] })
    render(<LeadImportsTable />)

    capturedOnAction?.(deleteAction, row)

    await waitFor(() => expect(bulkDeleteMock).toHaveBeenCalled())
    expect(refreshSpy).not.toHaveBeenCalled()
    expect(toastError).toHaveBeenCalled()
  })
})
