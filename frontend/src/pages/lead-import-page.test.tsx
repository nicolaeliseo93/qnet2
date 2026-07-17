import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import LeadImportPage from '@/pages/lead-import-page'

/**
 * Spec 0034 AC-015: the page gates the wizard behind `leads.import` via
 * `<Can>` — UI-only, the backend re-checks the same ability fail-closed on
 * every write endpoint. `PageHeader` (needs router/query context for its
 * breadcrumb) and `ImportWizard` (its own suite already covers the
 * orchestration) are stubbed, mirroring `pages/registries-page.test.tsx`
 * and `features/projects/projects-view.test.tsx`.
 */

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ title }: { title?: string }) => <div>{title}</div>,
}))

vi.mock('@/features/imports/wizard/import-wizard', () => ({
  ImportWizard: ({ domain }: { domain: string }) => <div data-testid="import-wizard">{domain}</div>,
}))

const canMock = vi.fn()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('LeadImportPage', () => {
  it('shows the forbidden fallback and does not mount the wizard without leads.import', () => {
    canMock.mockReturnValue(false)

    render(<LeadImportPage />)

    expect(screen.getByText("You don't have permission to import leads.")).toBeInTheDocument()
    expect(screen.queryByTestId('import-wizard')).not.toBeInTheDocument()
  })

  it('mounts the wizard for the leads domain when leads.import is granted', () => {
    canMock.mockReturnValue(true)

    render(<LeadImportPage />)

    expect(screen.getByTestId('import-wizard')).toHaveTextContent('leads')
    expect(screen.queryByText("You don't have permission to import leads.")).not.toBeInTheDocument()
  })
})
