import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { CellValueChangedEvent } from 'ag-grid-community'
import i18n from '@/i18n'
import { useTableCellEdit } from '@/features/table/use-table-cell-edit'
import type { TableRow } from '@/features/table/types'

/**
 * Spec 0053 AC-018/019/020/021: the generic table's inline-edit hook PATCHes
 * the edited cell and swaps the row for the server's re-mapped copy on
 * success, reverts to the previous value and toasts the server's message on
 * failure, and never calls the network for a no-op edit. Mirrors the import
 * wizard's own cell-edit hook test (`use-review-rows.test.tsx`), the only
 * prior precedent for this cycle in the repo.
 */

const updateTableCellMock = vi.fn()
const toastErrorMock = vi.fn()

vi.mock('@/features/table/api', () => ({
  updateTableCell: (...args: unknown[]) => updateTableCellMock(...args),
}))

vi.mock('sonner', () => ({
  toast: { error: (...args: unknown[]) => toastErrorMock(...args) },
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function row(overrides: Partial<TableRow> = {}): TableRow {
  return { id: 7, actions: [], editable: true, name: 'before', ...overrides }
}

function cellValueChangedEvent(overrides: {
  colId: string
  data: TableRow
  oldValue: unknown
  newValue: unknown
}): CellValueChangedEvent<TableRow> {
  return {
    column: { getColId: () => overrides.colId },
    data: overrides.data,
    oldValue: overrides.oldValue,
    newValue: overrides.newValue,
    node: { setData: vi.fn() },
  } as unknown as CellValueChangedEvent<TableRow>
}

beforeEach(() => {
  updateTableCellMock.mockReset()
  toastErrorMock.mockReset()
})

describe('useTableCellEdit', () => {
  it('PATCHes the edited column and swaps the row for the server copy on success (AC-018/020)', async () => {
    const updatedRow = row({ name: 'after' })
    updateTableCellMock.mockResolvedValue(updatedRow)

    const { result } = renderHook(() => useTableCellEdit('opportunities'), { wrapper: wrapper() })
    const event = cellValueChangedEvent({ colId: 'name', data: row(), oldValue: 'before', newValue: 'after' })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(updateTableCellMock).toHaveBeenCalledWith('opportunities', 7, { column: 'name', value: 'after' }),
    )
    await waitFor(() => expect(event.node.setData).toHaveBeenCalledWith(updatedRow))
  })

  it('reverts the cell and toasts the server message on failure (AC-019)', async () => {
    updateTableCellMock.mockRejectedValue({
      isAxiosError: true,
      response: { status: 403, data: { message: 'You cannot edit this field.' } },
    })

    const { result } = renderHook(() => useTableCellEdit('opportunities'), { wrapper: wrapper() })
    const data = row()
    const event = cellValueChangedEvent({ colId: 'name', data, oldValue: 'before', newValue: 'attempted' })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() => expect(event.node.setData).toHaveBeenCalledWith({ ...data, name: 'before' }))
    expect(toastErrorMock).toHaveBeenCalledWith('You cannot edit this field.')
  })

  it('falls back to a generic message when the server response carries none', async () => {
    updateTableCellMock.mockRejectedValue(new Error('network error'))
    await i18n.changeLanguage('en')

    const { result } = renderHook(() => useTableCellEdit('opportunities'), { wrapper: wrapper() })
    const event = cellValueChangedEvent({ colId: 'name', data: row(), oldValue: 'before', newValue: 'attempted' })

    act(() => result.current.handleCellValueChanged(event))

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('Unable to save the change.'))
  })

  it('does nothing when the value did not actually change (AC-021)', () => {
    const { result } = renderHook(() => useTableCellEdit('opportunities'), { wrapper: wrapper() })
    const event = cellValueChangedEvent({ colId: 'name', data: row(), oldValue: 'before', newValue: 'before' })

    act(() => result.current.handleCellValueChanged(event))

    expect(updateTableCellMock).not.toHaveBeenCalled()
  })
})
