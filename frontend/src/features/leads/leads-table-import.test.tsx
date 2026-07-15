import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { LeadsTable } from '@/features/leads/leads-table'

/**
 * Spec 0033 (F5 lane, AC-018 UI wiring): the "Import leads"/"Import history"
 * actions in the table's options menu are gated by `leads.import` and
 * navigate to the wizard/history pages instead of opening the legacy
 * `<ImportDialog>`. Split into its own suite (rather than extending
 * `leads-table.test.tsx`) because that file's `TableView` stub mutates a
 * module-scope `capturedOnAction` variable during render (an established
 * pattern also used by `campaigns-table.test.tsx`/`projects-table.test.tsx`)
 * — combining that with a second hook-consuming component in the same file
 * (a `useLocation` probe, needed here to assert the navigation target)
 * trips a false positive in `react-hooks/globals`. Neither concern needs the
 * other's fixtures, so a dedicated file sidesteps it cleanly.
 */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

vi.mock('@/features/table/table-view', () => ({
  TableView: ({ importSlot }: { importSlot?: ReactNode }) => (
    <div role="region" aria-label="table-leads">
      <DropdownMenu>
        <DropdownMenuTrigger>Table options</DropdownMenuTrigger>
        <DropdownMenuContent>{importSlot}</DropdownMenuContent>
      </DropdownMenu>
    </div>
  ),
}))

/**
 * Radix' DropdownMenu trigger opens on `pointerdown`, not `click`, so a plain
 * `fireEvent.click` leaves the panel closed in jsdom. Drive the real pointer
 * sequence instead (mirrors `companies-table.test.tsx`).
 */
function openOptionsMenu() {
  fireEvent.pointerDown(screen.getByRole('button', { name: 'Table options' }), {
    button: 0,
    ctrlKey: false,
  })
}

function LocationProbe() {
  const { pathname } = useLocation()
  return <span>location:{pathname}</span>
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/leads']}>
        <LocationProbe />
        <Routes>
          <Route path="/leads" element={<LeadsTable />} />
          <Route path="*" element={null} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('LeadsTable — Import Lead wiring (AC-018 UI)', () => {
  it('renders the Import leads and Import history actions with leads.import', () => {
    renderTable()
    openOptionsMenu()

    expect(screen.getByRole('menuitem', { name: 'Import leads' })).toBeInTheDocument()
    expect(screen.getByRole('menuitem', { name: 'Import history' })).toBeInTheDocument()
  })

  it('does not render the import actions without leads.import', () => {
    canMock.mockReturnValue(false)

    renderTable()
    openOptionsMenu()

    expect(screen.queryByRole('menuitem', { name: 'Import leads' })).not.toBeInTheDocument()
    expect(screen.queryByRole('menuitem', { name: 'Import history' })).not.toBeInTheDocument()
  })

  it('navigates to /leads/import when Import leads is selected', async () => {
    renderTable()
    openOptionsMenu()

    fireEvent.click(screen.getByRole('menuitem', { name: 'Import leads' }))

    await waitFor(() => expect(screen.getByText('location:/leads/import')).toBeInTheDocument())
  })

  it('navigates to /leads/import/history when Import history is selected', async () => {
    renderTable()
    openOptionsMenu()

    fireEvent.click(screen.getByRole('menuitem', { name: 'Import history' }))

    await waitFor(() =>
      expect(screen.getByText('location:/leads/import/history')).toBeInTheDocument(),
    )
  })
})
