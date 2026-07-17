import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RegistriesTable } from '@/features/registries/registries-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * AC-A1 (spec 0022) — the Registries adapter no longer opens a Sheet: the view
 * and edit row actions and the "New" button navigate to the dedicated pages.
 * The generic `<TableView>` and the app chrome are stubbed; the stub exposes
 * the row actions as buttons so the adapter's `onAction` wiring runs for real.
 */
const canMock = vi.fn<(permission: string) => boolean>()
// Default open mode for registries (page, spec 0022/0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page',
}))

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

const ROW: TableRow = { id: 12, actions: ['view', 'edit'] }
const action = (key: string): TableActionDefinition => ({
  key,
  label: key,
  icon: key,
  type: 'action',
  confirm: false,
})

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction: RowActionHandler }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction(action('view'), ROW)}>
          row-view
        </button>
        <button type="button" onClick={() => onAction(action('edit'), ROW)}>
          row-edit
        </button>
      </div>
    )
  }),
}))

function LocationProbe() {
  const { pathname } = useLocation()
  return <span>location:{pathname}</span>
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/registries']}>
        <LocationProbe />
        <Routes>
          <Route path="/registries" element={<RegistriesTable />} />
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

describe('RegistriesTable — navigation to the dedicated pages (AC-A1)', () => {
  it('navigates to the detail page on the view row action', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'row-view' }))

    expect(screen.getByText('location:/registries/12')).toBeInTheDocument()
  })

  it('navigates to the edit page on the edit row action', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'row-edit' }))

    expect(screen.getByText('location:/registries/12/edit')).toBeInTheDocument()
  })

  it('navigates to the create page from the New registry button', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: /new registry/i }))

    expect(screen.getByText('location:/registries/new')).toBeInTheDocument()
  })

  it('hides the New registry button without registries.create', () => {
    canMock.mockImplementation((permission) => permission !== 'registries.create')

    renderTable()

    expect(screen.queryByRole('button', { name: /new registry/i })).not.toBeInTheDocument()
  })
})
