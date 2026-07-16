import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import i18n from '@/i18n'
import LeadImportHistoryPage from '@/pages/lead-import-history-page'

/**
 * Spec 0034 AC-011: the history page is the landing of the Import module —
 * gated behind `import-runs.viewAny`, a "New import" action gated on its own
 * `import-runs.create`, the stats toggle + panel (domain `import-runs`) and
 * the backend-driven history table (export lives inside `<TableView>` itself,
 * covered by that component's own suite). `PageHeader` (needs router/query
 * context for its breadcrumb), `LeadImportsTable` and `ModuleStatsPanel` are
 * stubbed — their own suites cover the breadcrumb/grid/panel, mirroring
 * `pages/lead-import-page.test.tsx`.
 */

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ title, actions }: { title?: string; actions?: ReactNode }) => (
    <div>
      <span>{title}</span>
      {actions}
    </div>
  ),
}))

vi.mock('@/features/imports/lead-imports-table', () => ({
  LeadImportsTable: () => <div role="region" aria-label="table-import-runs" />,
}))

const statsPanelMock = vi.fn()
vi.mock('@/features/stats/module-stats-panel', () => ({
  ModuleStatsPanel: ({ domain, isOpen }: { domain: string; isOpen: boolean }) => {
    statsPanelMock(domain, isOpen)
    return null
  },
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

function LocationProbe() {
  const { pathname } = useLocation()
  return <span>location:{pathname}</span>
}

function renderPage() {
  return render(
    <MemoryRouter initialEntries={['/imports']}>
      <LocationProbe />
      <Routes>
        <Route path="/imports" element={<LeadImportHistoryPage />} />
        <Route path="*" element={null} />
      </Routes>
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  statsPanelMock.mockClear()
})

describe('LeadImportHistoryPage', () => {
  it('shows the forbidden fallback and does not mount the table without import-runs.viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(screen.getByText("You don't have permission to view imports.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-import-runs' })).not.toBeInTheDocument()
  })

  it('mounts the stats toggle and the history table with import-runs.viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'import-runs.viewAny')

    renderPage()

    expect(screen.queryByText('Import history')).not.toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'table-import-runs' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Statistics' })).toBeInTheDocument()
    expect(statsPanelMock).toHaveBeenCalledWith('import-runs', false)
  })

  it('does not show "New import" without import-runs.create', () => {
    canMock.mockImplementation((permission) => permission === 'import-runs.viewAny')

    renderPage()

    expect(screen.queryByRole('button', { name: 'New import' })).not.toBeInTheDocument()
  })

  it('navigates to the wizard when "New import" is clicked, with import-runs.create', () => {
    canMock.mockImplementation(
      (permission) => permission === 'import-runs.viewAny' || permission === 'import-runs.create',
    )

    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'New import' }))

    expect(screen.getByText('location:/imports/new')).toBeInTheDocument()
  })
})
