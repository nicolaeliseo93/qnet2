import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { I18nextProvider, useTranslation } from 'react-i18next'
import i18n from '@/i18n'
import { imports } from '@/i18n/locales/en-imports'
import { TooltipProvider } from '@/components/ui/tooltip'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { CompanySitesTable } from '@/features/company-sites/company-sites-table'

/**
 * Spec 0020 AC-014/AC-015: the page's `<TableView domain="company-sites">`
 * mounts with its renderer map, the "new site" affordance is gated on
 * `company-sites.create`, and the generic import action is gated on
 * `company-sites.import` (mirrors `companies-table.test.tsx`'s pattern).
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

vi.mock('@/features/imports/api', () => ({
  uploadImport: vi.fn(),
  getImportRun: vi.fn(),
  confirmImport: vi.fn(),
  downloadImportTemplate: vi.fn(),
  downloadImportErrorReport: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }))

let capturedDomain: string | undefined
vi.mock('@/features/table/table-view', () => ({
  TableView: ({
    domain,
    importSlot,
  }: {
    domain: string
    importSlot?: React.ReactNode
  }) => {
    capturedDomain = domain
    const { t } = useTranslation()
    return (
      <DropdownMenu>
        <DropdownMenuTrigger>{t('table.options')}</DropdownMenuTrigger>
        <DropdownMenuContent>{importSlot}</DropdownMenuContent>
      </DropdownMenu>
    )
  },
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { imports }, true, true)
})

/**
 * Radix' DropdownMenu trigger opens on `pointerdown`, not `click`, so a plain
 * `fireEvent.click` leaves the panel closed in jsdom. Drive the real pointer
 * sequence instead.
 */
function openOptionsMenu() {
  fireEvent.pointerDown(screen.getByRole('button', { name: 'Table options' }), {
    button: 0,
    ctrlKey: false,
  })
}

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

  it('renders the Import action in the options menu when the user has company-sites.import', () => {
    canMock.mockImplementation((permission) => permission === 'company-sites.import')
    renderCompanySitesTable()
    openOptionsMenu()
    expect(screen.getByRole('menuitem', { name: 'Import' })).toBeInTheDocument()
  })

  it('does not render the Import action without company-sites.import', () => {
    canMock.mockImplementation(() => false)
    renderCompanySitesTable()
    openOptionsMenu()
    expect(screen.queryByRole('menuitem', { name: 'Import' })).not.toBeInTheDocument()
  })
})
