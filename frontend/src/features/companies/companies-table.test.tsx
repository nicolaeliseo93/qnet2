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
import { CompaniesTable } from '@/features/companies/companies-table'

/**
 * Spec 0012 AC-018 (updated: the Import action now lives inside the table's
 * options menu, not a standalone toolbar button): the generic `importSlot`
 * injected by the Companies adapter must render only when the current user
 * holds `companies.import`. Heavy, unrelated collaborators are mocked so the
 * test isolates the gating behavior: `TableView` (SSRM grid + backend config
 * fetch) collapses to a stub that mounts the injected `importSlot` inside a
 * real `DropdownMenu` (so the `DropdownMenuItem` it renders has the Radix
 * menu context it needs), and the breadcrumb (router + navigation query) is
 * stubbed out entirely.
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

vi.mock('@/features/companies/api', () => ({
  deleteCompany: vi.fn(),
  fetchCompany: vi.fn(),
}))

vi.mock('@/features/imports/api', () => ({
  uploadImport: vi.fn(),
  getImportRun: vi.fn(),
  confirmImport: vi.fn(),
  downloadImportTemplate: vi.fn(),
  downloadImportErrorReport: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }))

vi.mock('@/features/table/table-view', () => ({
  TableView: ({ importSlot }: { importSlot?: React.ReactNode }) => {
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
  // Defensive: guarantees the `imports` module resolves even if the shared
  // i18n index (`en.ts`) has not (yet) merged it in this working tree — see
  // the handoff note on the `imports` i18n wiring regression.
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

function renderCompaniesTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <I18nextProvider i18n={i18n}>
        <TooltipProvider>
          <CompaniesTable />
        </TooltipProvider>
      </I18nextProvider>
    </QueryClientProvider>,
  )
}

describe('CompaniesTable import gating (AC-018)', () => {
  it('renders the Import action in the options menu when the user has companies.import', () => {
    canMock.mockImplementation((permission) => permission === 'companies.import')

    renderCompaniesTable()
    openOptionsMenu()

    expect(screen.getByRole('menuitem', { name: 'Import' })).toBeInTheDocument()
  })

  it('does not render the Import action without companies.import', () => {
    canMock.mockImplementation(() => false)

    renderCompaniesTable()
    openOptionsMenu()

    expect(screen.queryByRole('menuitem', { name: 'Import' })).not.toBeInTheDocument()
  })
})

