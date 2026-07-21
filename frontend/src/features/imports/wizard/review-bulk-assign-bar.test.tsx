import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { ReviewBulkAssignBar } from '@/features/imports/wizard/review-bulk-assign-bar'

/**
 * Compact toolbar shown above the review grid while the SSRM selection is
 * non-empty: selection count (or "All"), an operator picker, a site picker
 * and a single "Assign" action applying whichever field(s) are set.
 * Assign-only — no bulk clear, the single-row cells already cover it.
 */

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
    <button type="button" aria-label={labels.triggerLabel} onClick={() => onChange(labels.triggerLabel === 'Assign operator…' ? 42 : 84)}>
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

describe('ReviewBulkAssignBar', () => {
  it('shows the selected-count label for a partial selection', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1', '2', '3'] }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByText('3 row(s) selected')).toBeInTheDocument()
  })

  it('shows the "all selected" label for a select-all selection with nothing excluded', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: true, toggledNodes: [] }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByText('All rows selected')).toBeInTheDocument()
  })

  it('shows the excluded count for a select-all selection with some rows excluded', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: true, toggledNodes: ['9'] }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByText('All rows selected (1 excluded)')).toBeInTheDocument()
  })

  it('renders both an operator and a site picker', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1'] }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByRole('button', { name: 'Assign operator…' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign site…' })).toBeInTheDocument()
  })

  it('disables Assign until at least one of operator/site is picked', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1'] }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()

    fireEvent.click(screen.getByRole('button', { name: 'Assign operator…' }))
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()
  })

  it('calls onAssign with only the operator id when only the operator is picked', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    render(<ReviewBulkAssignBar selection={{ selectAll: false, toggledNodes: ['1'] }} onAssign={onAssign} />)

    fireEvent.click(screen.getByRole('button', { name: 'Assign operator…' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() => expect(onAssign).toHaveBeenCalledWith({ operatorId: 42, siteId: null }))
  })

  it('calls onAssign with only the site id when only the site is picked', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    render(<ReviewBulkAssignBar selection={{ selectAll: false, toggledNodes: ['1'] }} onAssign={onAssign} />)

    fireEvent.click(screen.getByRole('button', { name: 'Assign site…' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() => expect(onAssign).toHaveBeenCalledWith({ operatorId: null, siteId: 84 }))
  })

  it('calls onAssign with both ids when both are picked, and resets both picks on success', async () => {
    const onAssign = vi.fn().mockResolvedValue(undefined)
    render(<ReviewBulkAssignBar selection={{ selectAll: false, toggledNodes: ['1'] }} onAssign={onAssign} />)

    fireEvent.click(screen.getByRole('button', { name: 'Assign operator…' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign site…' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() => expect(onAssign).toHaveBeenCalledWith({ operatorId: 42, siteId: 84 }))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled())
  })

  it('keeps the picks when onAssign rejects (already surfaced by the caller)', async () => {
    const onAssign = vi.fn().mockRejectedValue(new Error('failed'))
    render(<ReviewBulkAssignBar selection={{ selectAll: false, toggledNodes: ['1'] }} onAssign={onAssign} />)

    fireEvent.click(screen.getByRole('button', { name: 'Assign operator…' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() => expect(onAssign).toHaveBeenCalledTimes(1))
    expect(screen.getByRole('button', { name: 'Assign' })).not.toBeDisabled()
  })

  it('exposes an accessible toolbar landmark', () => {
    render(
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1'] }}
        onAssign={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(
      screen.getByRole('toolbar', { name: 'Bulk-assign operator/site to the selected rows' }),
    ).toBeInTheDocument()
  })
})
