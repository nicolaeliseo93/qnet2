import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import OperationalSitesPage from '@/pages/operational-sites-page'

// This suite exercises the default modal behaviour; force the resolved open
// mode so it never depends on an AuthProvider (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

/**
 * AC-015 — permission gating of the Operational Sites page. The generic
 * `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`, which pulls
 * in the router-aware breadcrumb) are framework pieces outside this
 * microtask's ownership: they are stubbed so the suite stays focused on what
 * THIS adapter is responsible for — wiring `<Can>` around the table and
 * mounting `<TableView domain="operational-sites">` with the right domain.
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
  TableView: forwardRef<{ refresh: () => void }, { domain: string }>(
    function TableViewStub({ domain }, ref) {
      useImperativeHandle(ref, () => ({ refresh: () => {} }))
      return <div role="region" aria-label={`table-${domain}`} />
    },
  ),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <OperationalSitesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
})

describe('OperationalSitesPage — permission gating (AC-015)', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText("You don't have permission to view operational sites."),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('region', { name: 'table-operational-sites' }),
    ).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="operational-sites"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'operational-sites.viewAny')

    renderPage()

    expect(
      screen.getByRole('region', { name: 'table-operational-sites' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view operational sites."),
    ).not.toBeInTheDocument()
  })
})
