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
import { UsersTable } from '@/features/users/users-table'

/**
 * Spec 0012 follow-up: the generic `importSlot` injected by the Users adapter
 * (mirrors the Companies AC-018 gating test) must render only when the current
 * user holds `users.import`. `TableView` collapses to a stub that mounts the
 * injected `importSlot` inside a real `DropdownMenu` (so the `DropdownMenuItem`
 * it renders has the Radix menu context it needs); the breadcrumb and auth
 * context are stubbed out entirely.
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

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: null }),
}))

vi.mock('@/routes/breadcrumbs', () => ({
  AppBreadcrumbs: () => null,
}))

vi.mock('@/features/users/api', () => ({
  deleteUser: vi.fn(),
  fetchUser: vi.fn(),
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
  // i18n index (`en.ts`) has not (yet) merged it in this working tree.
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

function renderUsersTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <I18nextProvider i18n={i18n}>
        <TooltipProvider>
          <UsersTable />
        </TooltipProvider>
      </I18nextProvider>
    </QueryClientProvider>,
  )
}

describe('UsersTable import gating', () => {
  it('renders the Import action in the options menu when the user has users.import', () => {
    canMock.mockImplementation((permission) => permission === 'users.import')

    renderUsersTable()
    openOptionsMenu()

    expect(screen.getByRole('menuitem', { name: 'Import' })).toBeInTheDocument()
  })

  it('does not render the Import action without users.import', () => {
    canMock.mockImplementation(() => false)

    renderUsersTable()
    openOptionsMenu()

    expect(screen.queryByRole('menuitem', { name: 'Import' })).not.toBeInTheDocument()
  })
})
