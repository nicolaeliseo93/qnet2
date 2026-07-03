import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { FilterViewsControl } from '@/features/table/filter-views-control'
import type { TableFilterView } from '@/features/table/types'

const listFilterViews = vi.fn()
const createFilterView = vi.fn()
const deleteFilterView = vi.fn()

vi.mock('@/features/table/filter-views-api', () => ({
  listFilterViews: (...args: unknown[]) => listFilterViews(...args),
  createFilterView: (...args: unknown[]) => createFilterView(...args),
  updateFilterView: vi.fn(),
  deleteFilterView: (...args: unknown[]) => deleteFilterView(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const OWNED_VIEW: TableFilterView = {
  id: 1,
  name: 'My admins',
  filters: { roles: { filterType: 'set', values: ['admin'] } },
  visibility: 'private',
  owned: true,
  owner_name: null,
}

const SHARED_VIEW: TableFilterView = {
  id: 2,
  name: 'Team overview',
  filters: { status: { filterType: 'set', values: ['active'] } },
  visibility: 'shared',
  owned: false,
  owner_name: 'Jane Doe',
}

const CURRENT_FILTERS = { email: { filterType: 'text' } }

/**
 * Radix' DropdownMenu trigger opens on `pointerdown`, not `click`, so a plain
 * `fireEvent.click` leaves the panel closed in jsdom (see notification-bell).
 */
function openMenu() {
  fireEvent.pointerDown(screen.getByRole('button', { name: /Saved filters/ }), {
    button: 0,
    ctrlKey: false,
  })
}

function renderControl(
  views: TableFilterView[],
  currentFilters: Record<string, unknown> = CURRENT_FILTERS,
  onApply = vi.fn(),
) {
  listFilterViews.mockResolvedValue(views)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <ConfirmDialogProvider>
          <FilterViewsControl domain="users" currentFilters={currentFilters} onApply={onApply} />
        </ConfirmDialogProvider>
      </TooltipProvider>
    </QueryClientProvider>,
  )
  return { onApply }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  listFilterViews.mockReset()
  createFilterView.mockReset()
  deleteFilterView.mockReset()
})

describe('FilterViewsControl', () => {
  it('groups owned and shared-by-others views and applies a view on click', async () => {
    const { onApply } = renderControl([OWNED_VIEW, SHARED_VIEW])

    openMenu()

    expect(await screen.findByRole('menuitem', { name: /My admins/ })).toBeInTheDocument()
    expect(screen.getByText('My views')).toBeInTheDocument()
    expect(screen.getByText(/Shared by Jane Doe/)).toBeInTheDocument()

    fireEvent.click(screen.getByRole('menuitem', { name: /My admins/ }))
    expect(onApply).toHaveBeenCalledWith(OWNED_VIEW.filters)
  })

  it('shows the empty state when there are no saved views', async () => {
    renderControl([])

    openMenu()

    expect(await screen.findByText('No saved views yet.')).toBeInTheDocument()
  })

  it('deletes an owned view after confirmation', async () => {
    deleteFilterView.mockResolvedValue(undefined)
    renderControl([OWNED_VIEW])

    openMenu()
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Delete view' }))

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(deleteFilterView).toHaveBeenCalledWith('users', 1))
  })

  it('does not delete when the confirmation is dismissed', async () => {
    renderControl([OWNED_VIEW])

    openMenu()
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Delete view' }))

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    await waitFor(() =>
      expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument(),
    )
    expect(deleteFilterView).not.toHaveBeenCalled()
  })

  it('saves the current filters inline, honoring the chosen visibility', async () => {
    createFilterView.mockResolvedValue({ ...OWNED_VIEW, name: 'Weekly' })
    renderControl([])

    openMenu()

    // Save stays disabled until the view has a name.
    const saveButton = await screen.findByRole('button', { name: 'Save view' })
    expect(saveButton).toBeDisabled()

    fireEvent.change(screen.getByRole('textbox', { name: 'View name' }), {
      target: { value: 'Weekly' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Shared' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save view' }))

    await waitFor(() =>
      expect(createFilterView).toHaveBeenCalledWith('users', {
        name: 'Weekly',
        filters: CURRENT_FILTERS,
        visibility: 'shared',
      }),
    )
  })

  it('offers a hint instead of the form when there are no filters to save', async () => {
    renderControl([], {})

    openMenu()

    expect(
      await screen.findByText('Apply a filter first to save it as a view.'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Save view' })).not.toBeInTheDocument()
  })
})
