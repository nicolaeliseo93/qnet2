import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { GridReadyEvent, SelectionChangedEvent } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { ReviewGrid } from '@/features/imports/wizard/review-grid'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/**
 * Checkbox multi-selection over the SSRM review grid drives a bulk operator
 * assign: the toolbar appears only once AG Grid's own server-side selection
 * state is non-empty, "Assegna" maps that state 1:1 onto the bulk PATCH
 * payload (`buildBulkAssignPayload`, unit-tested in
 * `use-review-rows.test.tsx`), and a successful assign refreshes the SSRM
 * cache and clears the selection. `AgGridReact` is stubbed — this codebase
 * never mounts the real grid in tests (see `data-table.test.tsx`); the stub
 * exposes the grid api AG Grid would otherwise own and lets the test fire
 * `onSelectionChanged` directly.
 */

let capturedProps: Record<string, unknown> = {}

const refreshServerSideMock = vi.fn()
const setServerSideSelectionStateMock = vi.fn()

vi.mock('ag-grid-react', () => ({
  AgGridReact: (props: Record<string, unknown>) => {
    capturedProps = props
    const onGridReady = props.onGridReady as ((event: GridReadyEvent) => void) | undefined
    onGridReady?.({
      api: {
        refreshServerSide: refreshServerSideMock,
        setServerSideSelectionState: setServerSideSelectionStateMock,
      },
    } as unknown as GridReadyEvent)
    return <div data-testid="ag-grid-stub" />
  },
}))

const handleBulkAssignMock = vi.fn()

vi.mock('@/features/imports/wizard/use-review-rows', () => ({
  useReviewRows: () => ({
    datasource: {},
    handleCellValueChanged: vi.fn(),
    handleResolutionChange: vi.fn(),
    handleApplyGeo: vi.fn(),
    handleApplyOperator: vi.fn(),
    handleApplySite: vi.fn(),
    handleBulkAssign: (...args: unknown[]) => handleBulkAssignMock(...args),
    isSaving: false,
    hasSaveError: false,
  }),
  buildBulkAssignPayload: (
    selection: { selectAll: boolean; toggledNodes: string[] },
    { operatorId, siteId }: { operatorId: number | null; siteId: number | null },
  ) => ({
    ...(operatorId != null ? { operator_id: operatorId } : {}),
    ...(siteId != null ? { operational_site_id: siteId } : {}),
    select_all: selection.selectAll,
    row_ids: selection.toggledNodes.map(Number),
  }),
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      onClick={() => onChange(labels.triggerLabel === 'Assign operator…' ? 42 : 84)}
    >
      {value ?? 'none'}
    </button>
  ),
}))

function baseRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 7,
    resource: 'leads',
    status: 'reviewing',
    original_filename: 'leads.csv',
    total_rows: 10,
    valid_rows: 8,
    warning_rows: 1,
    error_rows: 1,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 0,
    detected_columns: [{ key: 'Email', name: 'Email', index: 0, duplicate: false }],
    column_mapping: { Email: 'email' },
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: null,
    fields: [{ id: 'email', label: 'Email', required: true, group: 'contact', type: 'string' }],
    global_fields: [],
    dedup_modes: ['create_new'],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  capturedProps = {}
  refreshServerSideMock.mockReset()
  setServerSideSelectionStateMock.mockReset()
  handleBulkAssignMock.mockReset()
})

function fireSelectionChanged(state: { selectAll: boolean; toggledNodes: string[] } | null) {
  const onSelectionChanged = capturedProps.onSelectionChanged as
    | ((event: SelectionChangedEvent) => void)
    | undefined
  act(() => {
    onSelectionChanged?.({ api: { getServerSideSelectionState: () => state } } as unknown as SelectionChangedEvent)
  })
}

describe('ReviewGrid — bulk assign (operator + site)', () => {
  it('hides the bulk-assign bar with no selection', () => {
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    expect(screen.queryByRole('toolbar')).not.toBeInTheDocument()
  })

  it('shows the bar once the selection is non-empty (partial selection)', () => {
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1', '2'] })
    expect(screen.getByRole('toolbar')).toBeInTheDocument()
    expect(screen.getByText('2 row(s) selected')).toBeInTheDocument()
  })

  it('sends select_all: false with the included row ids for a partial selection', async () => {
    handleBulkAssignMock.mockResolvedValue({ updated: 2 })
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1', '2'] })

    fireEvent.click(screen.getByRole('button', { name: 'Assign operator…' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(handleBulkAssignMock).toHaveBeenCalledWith({
        operator_id: 42,
        select_all: false,
        row_ids: [1, 2],
      }),
    )
  })

  it('sends select_all: true with the excluded row ids for a select-all selection', async () => {
    handleBulkAssignMock.mockResolvedValue({ updated: 40 })
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: true, toggledNodes: ['5'] })

    fireEvent.click(screen.getByRole('button', { name: 'Assign operator…' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(handleBulkAssignMock).toHaveBeenCalledWith({
        operator_id: 42,
        select_all: true,
        row_ids: [5],
      }),
    )
  })

  it('sends both operator_id and operational_site_id when both are picked', async () => {
    handleBulkAssignMock.mockResolvedValue({ updated: 2 })
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1', '2'] })

    fireEvent.click(screen.getByRole('button', { name: 'Assign operator…' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign site…' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(handleBulkAssignMock).toHaveBeenCalledWith({
        operator_id: 42,
        operational_site_id: 84,
        select_all: false,
        row_ids: [1, 2],
      }),
    )
  })

  it('refreshes the SSRM cache and clears the selection after a successful assign', async () => {
    handleBulkAssignMock.mockResolvedValue({ updated: 2 })
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1', '2'] })

    fireEvent.click(screen.getByRole('button', { name: 'Assign operator…' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(setServerSideSelectionStateMock).toHaveBeenCalledWith({ selectAll: false, toggledNodes: [] }),
    )
    expect(refreshServerSideMock).toHaveBeenCalledWith({ purge: true })
  })

  it('never wires selection in readOnly mode, so the bar never appears', () => {
    render(<ReviewGrid domain="leads" run={baseRun()} readOnly />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1'] })
    expect(screen.queryByRole('toolbar')).not.toBeInTheDocument()
  })
})
