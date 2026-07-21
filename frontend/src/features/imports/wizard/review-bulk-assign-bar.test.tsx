import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { ReviewBulkAssignBar } from '@/features/imports/wizard/review-bulk-assign-bar'

/**
 * Compact toolbar shown above the review grid while the SSRM selection is
 * non-empty: selection count (or "All") plus a single trigger opening the
 * SHARED "Assegna operatori" popup (spec 0048 AC-050) — the real
 * `AssignOperatorsDialog`, with only its `AsyncPaginatedSelect` pickers
 * stubbed (mirrors `assign-operators-dialog.test.tsx`).
 */

const SITE_PICK_ID = 7
const OPERATOR_PICK_ID = 42

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    value,
    onChange,
    labels,
  }: {
    resource: string
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      onClick={() => onChange(resource === 'operational-sites' ? SITE_PICK_ID : OPERATOR_PICK_ID)}
    >
      {value ?? 'none'}
    </button>
  ),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

function openDialog() {
  fireEvent.click(screen.getByRole('button', { name: 'Assign operators' }))
}

describe('ReviewBulkAssignBar — selection label', () => {
  it('shows the selected-count label for a partial selection', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1', '2', '3'] }}
        totalRows={10}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByText('3 row(s) selected')).toBeInTheDocument()
  })

  it('shows the "all selected" label for a select-all selection with nothing excluded', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: true, toggledNodes: [] }}
        totalRows={10}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByText('All rows selected')).toBeInTheDocument()
  })

  it('shows the excluded count for a select-all selection with some rows excluded', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: true, toggledNodes: ['9'] }}
        totalRows={10}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByText('All rows selected (1 excluded)')).toBeInTheDocument()
  })

  it('exposes an accessible toolbar landmark', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1'] }}
        totalRows={10}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(
      screen.getByRole('toolbar', { name: 'Bulk-assign operator/site to the selected rows' }),
    ).toBeInTheDocument()
  })
})

describe('ReviewBulkAssignBar — opens the shared popup', () => {
  it('renders a trigger that opens the shared "Assegna operatori" popup', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1', '2'] }}
        totalRows={10}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )

    expect(screen.queryByText('2 lead(s) selected.')).not.toBeInTheDocument()
    openDialog()

    expect(screen.getByText('2 lead(s) selected.')).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Balanced split' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Assign to operator' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    expect(screen.getByRole('button', { name: 'Site' })).toBeInTheDocument()
  })

  it('passes the toggled-node count as selectionCount for a partial selection', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1', '2', '3'] }}
        totalRows={10}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    openDialog()
    expect(screen.getByText('3 lead(s) selected.')).toBeInTheDocument()
  })

  it('approximates selectionCount from totalRows minus excluded nodes on select-all', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: true, toggledNodes: ['9'] }}
        totalRows={10}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    openDialog()
    expect(screen.getByText('9 lead(s) selected.')).toBeInTheDocument()
  })

  it('calls onAssign with mode "balanced" (site only) and closes on success', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1'] }}
        totalRows={10}
        onAssign={onAssign}
      />,
    )
    openDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(onAssign).toHaveBeenCalledWith({ operational_site_id: SITE_PICK_ID, mode: 'balanced' }),
    )
    await waitFor(() => expect(screen.queryByText('1 lead(s) selected.')).not.toBeInTheDocument())
  })

  it('calls onAssign with mode "single" (site + operator)', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1'] }}
        totalRows={10}
        onAssign={onAssign}
      />,
    )
    openDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(onAssign).toHaveBeenCalledWith({
        operational_site_id: SITE_PICK_ID,
        mode: 'single',
        operator_id: OPERATOR_PICK_ID,
      }),
    )
  })

  it('keeps the popup open when onAssign rejects (already surfaced by the caller)', async () => {
    const onAssign = vi.fn().mockRejectedValue(new Error('failed'))
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1'] }}
        totalRows={10}
        onAssign={onAssign}
      />,
    )
    openDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() => expect(onAssign).toHaveBeenCalledTimes(1))
    expect(screen.getByText('1 lead(s) selected.')).toBeInTheDocument()
  })
})
