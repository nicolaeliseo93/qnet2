import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import LeadImportHistoryPage from '@/pages/lead-import-history-page'

/**
 * Spec 0033 AC-018/AC-026: the history page gates the table behind
 * `leads.import` (`<Can>`, UI-only — the backend re-checks the same ability
 * fail-closed on the generic table endpoints). `PageHeader` (needs router/query
 * context for its breadcrumb) and `LeadImportsTable` (the generic table, its own
 * suite covers the grid) are stubbed, mirroring `pages/lead-import-page.test.tsx`.
 */

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ title }: { title?: string }) => <div>{title}</div>,
}))

vi.mock('@/features/imports/lead-imports-table', () => ({
  LeadImportsTable: () => <div data-testid="import-history">lead-imports</div>,
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

  it('mounts the generic lead-imports table when leads.import is granted', () => {
    canMock.mockReturnValue(true)

    render(<LeadImportHistoryPage />)

    expect(screen.getByTestId('import-history')).toHaveTextContent('lead-imports')
    expect(screen.queryByText("You don't have permission to import leads.")).not.toBeInTheDocument()
  })
})
