import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import LeadImportHistoryPage from '@/pages/lead-import-history-page'

/**
 * Spec 0033 AC-018/AC-026: the history page gates the list behind
 * `leads.import` (`<Can>`, UI-only — the backend re-checks the same ability
 * fail-closed on `GET /imports/{domain}`). `PageHeader` (needs router/query
 * context for its breadcrumb) and `ImportHistory` (its own suite already
 * covers the data-fetching) are stubbed, mirroring `pages/lead-import-page.test.tsx`.
 */

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ title }: { title?: string }) => <div>{title}</div>,
}))

vi.mock('@/features/imports/wizard/import-history', () => ({
  ImportHistory: ({ domain }: { domain: string }) => <div data-testid="import-history">{domain}</div>,
}))

const canMock = vi.fn()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('LeadImportHistoryPage', () => {
  it('shows the forbidden fallback and does not mount the history without leads.import', () => {
    canMock.mockReturnValue(false)

    render(<LeadImportHistoryPage />)

    expect(screen.getByText("You don't have permission to import leads.")).toBeInTheDocument()
    expect(screen.queryByTestId('import-history')).not.toBeInTheDocument()
  })

  it('mounts the history for the leads domain when leads.import is granted', () => {
    canMock.mockReturnValue(true)

    render(<LeadImportHistoryPage />)

    expect(screen.getByTestId('import-history')).toHaveTextContent('leads')
    expect(screen.queryByText("You don't have permission to import leads.")).not.toBeInTheDocument()
  })
})
