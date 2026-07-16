import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { CompanySitesTable } from '@/features/company-sites/company-sites-table'

/**
 * Spec 0020 AC-014: the page's `<TableView domain="company-sites">` mounts
 * with its renderer map, and the "new site" affordance is gated on
 * `company-sites.create`.
 */

const canMock = vi.fn((permission: string) => Boolean(permission))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/routes/breadcrumbs', () => ({
  AppBreadcrumbs: () => null,
}))

vi.mock('@/features/company-sites/api', () => ({
  deleteCompanySite: vi.fn(),
  fetchCompanySite: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }))

let capturedDomain: string | undefined
vi.mock('@/features/table/table-view', () => ({
  TableView: ({ domain }: { domain: string }) => {
    capturedDomain = domain
    return null
  },
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderCompanySitesTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <I18nextProvider i18n={i18n}>
        <TooltipProvider>
          <CompanySitesTable />
        </TooltipProvider>
      </I18nextProvider>
    </QueryClientProvider>,
  )
}

describe('CompanySitesTable', () => {
  it('mounts the generic table on the "company-sites" domain', () => {
    canMock.mockImplementation(() => false)
    renderCompanySitesTable()
    expect(capturedDomain).toBe('company-sites')
  })

  it('shows the "New site" action only with company-sites.create', () => {
    canMock.mockImplementation((permission) => permission === 'company-sites.create')
    renderCompanySitesTable()
    expect(screen.getByRole('button', { name: 'New site' })).toBeInTheDocument()
  })

  it('hides the "New site" action without company-sites.create', () => {
    canMock.mockImplementation(() => false)
    renderCompanySitesTable()
    expect(screen.queryByRole('button', { name: 'New site' })).not.toBeInTheDocument()
  })
})
