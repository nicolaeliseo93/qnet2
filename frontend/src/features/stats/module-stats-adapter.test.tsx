import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { LeadsTable } from '@/features/leads/leads-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableRow } from '@/features/table/types'
import type { ModuleStats } from '@/features/stats/types'

/**
 * Spec 0026 AC-006/AC-007 — a table adapter (Leads stands in for the eleven,
 * which are wired identically) shows the toggle next to its "New" button, opens
 * the one generic panel, fetches nothing until the first open, and keeps the
 * KPIs in sync with the grid after a mutation.
 */

const fetchModuleStatsMock = vi.fn<() => Promise<ModuleStats>>()

vi.mock('@/features/stats/api', () => ({
  fetchModuleStats: () => fetchModuleStatsMock(),
  moduleStatsQueryKey: (domain: string) => ['stats', domain],
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const ROW: TableRow = { id: 33, actions: ['delete'], name: 'Jane Doe' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction: RowActionHandler }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button
          type="button"
          onClick={() =>
            onAction({ key: 'delete', label: 'actions.delete', icon: 'trash', type: 'danger' }, ROW)
          }
        >
          trigger-delete
        </button>
      </div>
    )
  }),
}))

vi.mock('@/features/leads/lead-form', () => ({ LeadForm: () => <div /> }))

const deleteLeadMock = vi.fn()
vi.mock('@/features/leads/api', () => ({
  deleteLead: (...args: unknown[]) => deleteLeadMock(...args),
  fetchLead: vi.fn(),
  leadDetailQueryKey: (id: number | null) => ['leads', 'detail', id],
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const LEADS_STATS: ModuleStats = {
  widgets: [
    {
      type: 'stat',
      key: 'total',
      label: 'leads.stats.total',
      value: 128,
      format: 'number',
      subtitle: null,
      icon: 'users',
    },
  ],
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>
      <LeadsTable />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  fetchModuleStatsMock.mockReset()
  fetchModuleStatsMock.mockResolvedValue(LEADS_STATS)
  deleteLeadMock.mockReset()
  deleteLeadMock.mockResolvedValue(undefined)
})

describe('Statistics panel inside a table adapter', () => {
  it('renders a collapsed toggle wired to the panel it controls (AC-006)', () => {
    renderTable()

    const toggle = screen.getByRole('button', { name: 'Statistics' })

    expect(toggle).toHaveAttribute('aria-expanded', 'false')
    expect(toggle).toHaveAttribute('aria-controls', 'stats-panel-leads')
    expect(screen.queryByRole('region', { name: 'Module statistics' })).not.toBeInTheDocument()
  })

  it('fetches nothing until the first open, then renders the panel (AC-007)', async () => {
    renderTable()

    expect(fetchModuleStatsMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Statistics' }))

    await waitFor(() => expect(fetchModuleStatsMock).toHaveBeenCalledTimes(1))
    expect(screen.getByRole('button', { name: 'Statistics' })).toHaveAttribute(
      'aria-expanded',
      'true',
    )
    expect(await screen.findByText('Leads')).toBeInTheDocument()
    expect(window.localStorage.getItem('stats-panel:leads')).toBe('true')
  })

  it('closes the panel again and persists the choice (AC-008)', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Statistics' }))
    expect(await screen.findByText('Leads')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Statistics' }))

    expect(screen.queryByRole('region', { name: 'Module statistics' })).not.toBeInTheDocument()
    expect(window.localStorage.getItem('stats-panel:leads')).toBe('false')
  })

  it('refetches the KPIs after a mutation while the panel is open', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Statistics' }))
    await waitFor(() => expect(fetchModuleStatsMock).toHaveBeenCalledTimes(1))

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() => expect(deleteLeadMock).toHaveBeenCalledWith(33))
    await waitFor(() => expect(fetchModuleStatsMock).toHaveBeenCalledTimes(2))
  })

  it('issues no request to /api/stats/* after a mutation while the panel is closed', async () => {
    renderTable()

    expect(fetchModuleStatsMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() => expect(deleteLeadMock).toHaveBeenCalledWith(33))
    expect(fetchModuleStatsMock).not.toHaveBeenCalled()
  })
})
