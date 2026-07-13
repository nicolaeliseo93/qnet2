import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { projects as projectsEn } from '@/i18n/locales/en-projects'
import { ProjectsTable } from '@/features/projects/projects-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * AC-040/041 (spec 0023): the Projects adapter navigates to the dedicated
 * pages instead of opening a Sheet (mirrors `RegistriesTable`, spec 0022).
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
      <MemoryRouter initialEntries={['/projects']}>
        <LocationProbe />
        <Routes>
          <Route path="/projects" element={<ProjectsTable />} />
          <Route path="*" element={null} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `projects` is not yet wired into `en.ts` (pending the wiring lane, see
  // handoff): registered here so the feature's own copy renders for real.
  i18n.addResourceBundle('en', 'translation', { projects: projectsEn }, true, true)
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('ProjectsTable — navigation to the dedicated pages (AC-040/041)', () => {
  it('navigates to the detail page on the view row action', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'row-view' }))

    expect(screen.getByText('location:/projects/12')).toBeInTheDocument()
  })

  it('navigates to the edit page on the edit row action', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'row-edit' }))

    expect(screen.getByText('location:/projects/12/edit')).toBeInTheDocument()
  })

  it('navigates to the create page from the New project button', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: /new project/i }))

    expect(screen.getByText('location:/projects/new')).toBeInTheDocument()
  })

  it('hides the New project button without projects.create', () => {
    canMock.mockImplementation((permission) => permission !== 'projects.create')

    renderTable()

    expect(screen.queryByRole('button', { name: /new project/i })).not.toBeInTheDocument()
  })
})
