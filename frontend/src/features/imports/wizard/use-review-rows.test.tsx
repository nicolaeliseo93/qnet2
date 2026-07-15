import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type {
  CellValueChangedEvent,
  IServerSideGetRowsParams,
} from 'ag-grid-community'
import { useReviewRows } from '@/features/imports/wizard/use-review-rows'
import type { ImportRunRowItem, ImportRunRowUpdateResult } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-023: the review grid's SSRM datasource loads staged rows, and
 * an inline edit PATCHes just the edited field, swapping the row for the
 * server's re-validated copy (or reverting it on failure).
 */

const getImportRunRowsMock = vi.fn()
const updateImportRunRowMock = vi.fn()

vi.mock('@/features/imports/wizard/api', () => ({
  getImportRunRows: (...args: unknown[]) => getImportRunRowsMock(...args),
  updateImportRunRow: (...args: unknown[]) => updateImportRunRowMock(...args),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function rowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return {
    id: 10,
    row_number: 1,
    status: 'error',
    is_edited: false,
    duplicate_of_id: null,
    values: { email: 'bad-email' },
    messages: ['Invalid email format'],
    ...overrides,
  }
}

function ssrmParams(overrides: Partial<IServerSideGetRowsParams['request']> = {}) {
  const success = vi.fn()
  const fail = vi.fn()
  return {
    params: {
      request: { startRow: 0, endRow: 25, sortModel: [], filterModel: null, ...overrides },
      success,
      fail,
    } as unknown as IServerSideGetRowsParams<ImportRunRowItem>,
    success,
    fail,
  }
}

function cellValueChangedEvent(overrides: {
  colId: string
  data: ImportRunRowItem
  oldValue: string
  newValue: string
}): CellValueChangedEvent<ImportRunRowItem> {
  return {
    column: { getColId: () => overrides.colId },
    data: overrides.data,
    oldValue: overrides.oldValue,
    newValue: overrides.newValue,
    node: { setData: vi.fn() },
  } as unknown as CellValueChangedEvent<ImportRunRowItem>
}

beforeEach(() => {
  getImportRunRowsMock.mockReset()
  updateImportRunRowMock.mockReset()
})

describe('useReviewRows — datasource', () => {
  it('forwards the SSRM request and resolves with rowData/rowCount', async () => {
    getImportRunRowsMock.mockResolvedValue({
      items: [rowItem()],
      pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 },
    })

    const { result } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const { params, success } = ssrmParams({ sortModel: [{ colId: 'status', sort: 'asc' }] })
    await act(async () => result.current.datasource.getRows(params))

    expect(getImportRunRowsMock).toHaveBeenCalledWith('leads', 7, {
      startRow: 0,
      endRow: 25,
      sortModel: [{ colId: 'status', sort: 'asc' }],
      filterModel: {},
    })
    expect(success).toHaveBeenCalledWith({ rowData: [rowItem()], rowCount: 1 })
  })

  it('fails the SSRM request when the API call rejects', async () => {
    getImportRunRowsMock.mockRejectedValue(new Error('network error'))

    const { result } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const { params, fail } = ssrmParams()
    await act(async () => result.current.datasource.getRows(params))

    expect(fail).toHaveBeenCalledTimes(1)
  })
})

describe('useReviewRows — inline edit', () => {
  it('PATCHes the edited field and swaps the row for the server copy, bubbling counts', async () => {
    const updatedRow = rowItem({ status: 'valid', is_edited: true, values: { email: 'mario@example.com' }, messages: [] })
    const counts = { total: 3, valid_rows: 2, warning_rows: 0, error_rows: 0, duplicate_rows: 0, modified_rows: 1 }
    const result: ImportRunRowUpdateResult = { row: updatedRow, counts }
    updateImportRunRowMock.mockResolvedValue(result)
    const onRowUpdated = vi.fn()

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const event = cellValueChangedEvent({
      colId: 'field:email',
      data: rowItem(),
      oldValue: 'bad-email',
      newValue: 'mario@example.com',
    })

    act(() => hookResult.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(updateImportRunRowMock).toHaveBeenCalledWith('leads', 7, 10, { email: 'mario@example.com' }),
    )
    await waitFor(() => expect(event.node.setData).toHaveBeenCalledWith(updatedRow))
    expect(onRowUpdated).toHaveBeenCalledWith(updatedRow, counts)
  })

  it('reverts the cell to its previous value when the PATCH fails', async () => {
    updateImportRunRowMock.mockRejectedValue(new Error('validation failed'))

    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const data = rowItem()
    const event = cellValueChangedEvent({ colId: 'field:email', data, oldValue: 'bad-email', newValue: 'still-bad' })

    act(() => hookResult.current.handleCellValueChanged(event))

    await waitFor(() =>
      expect(event.node.setData).toHaveBeenCalledWith({ ...data, values: { email: 'bad-email' } }),
    )
  })

  it('does nothing for a read-only column (status)', () => {
    const onRowUpdated = vi.fn()
    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated }),
      { wrapper: wrapper() },
    )

    const event = cellValueChangedEvent({ colId: 'status', data: rowItem(), oldValue: 'error', newValue: 'valid' })
    act(() => hookResult.current.handleCellValueChanged(event))

    expect(updateImportRunRowMock).not.toHaveBeenCalled()
    expect(onRowUpdated).not.toHaveBeenCalled()
  })

  it('does nothing when the value did not actually change', () => {
    const { result: hookResult } = renderHook(
      () => useReviewRows({ domain: 'leads', importRunId: 7, onRowUpdated: vi.fn() }),
      { wrapper: wrapper() },
    )

    const event = cellValueChangedEvent({
      colId: 'field:email',
      data: rowItem(),
      oldValue: 'bad-email',
      newValue: 'bad-email',
    })
    act(() => hookResult.current.handleCellValueChanged(event))

    expect(updateImportRunRowMock).not.toHaveBeenCalled()
  })
})
