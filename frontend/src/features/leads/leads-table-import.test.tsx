import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { LeadsTable } from '@/features/leads/leads-table'

/**
 * Import affordance on the Leads module. The inline import wizard (spec 0034
 * `importSlot`) stays fully removed — the import module is standalone. What the
 * Leads page now offers is a plain navigation button to that standalone module
 * (`/imports`), gated on `leads.import` (UI-only; the module re-checks
 * the same ability). This guard asserts both: no `importSlot` is threaded to
 * `<TableView>`, and the navigation button appears only when authorized.
 */

const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

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

const capturedProps: { importSlot?: ReactNode }[] = []

vi.mock('@/features/table/table-view', () => ({
  TableView: ({ importSlot }: { importSlot?: ReactNode }) => {
    capturedProps.push({ importSlot })
    return <div role="region" aria-label="table-leads" />
  },
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/leads']}>
        <LeadsTable />
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
  navigateMock.mockReset()
  capturedProps.length = 0
})

describe('LeadsTable — import affordance', () => {
  it('does not thread an importSlot prop to the generic table', () => {
    renderTable()

    expect(capturedProps).toHaveLength(1)
    expect(capturedProps[0].importSlot).toBeUndefined()
  })

  it('renders the import button and navigates to the standalone module when authorized', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Import leads' }))

    expect(navigateMock).toHaveBeenCalledWith('/imports')
  })

  it('hides the import button when the actor lacks leads.import', () => {
    canMock.mockImplementation((permission) => permission !== 'leads.import')
    renderTable()

    expect(screen.queryByRole('button', { name: 'Import leads' })).not.toBeInTheDocument()
  })
})
