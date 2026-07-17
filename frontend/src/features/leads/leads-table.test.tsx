import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { LeadsTable } from '@/features/leads/leads-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { LeadDetailWithPermissions } from '@/features/leads/types'

/**
 * Spec 0025 Parte B, AC-024 (mirrors projects AC-020/021/022/023) — the Leads
 * adapter now opens a resizable Sheet for view/edit/create instead of
 * navigating to the dedicated pages (those remain as deep-links, covered
 * separately by the page tests). The generic `<TableView>` (AG Grid + SSRM)
 * is stubbed with buttons that fire `onAction` for a fixed row, mirroring the
 * sheet-based adapters' suites (e.g. `SectorsTable`, `ProjectsTable`).
 */

const mockLead: LeadDetailWithPermissions = {
  id: 33,
  registry_id: 5,
  registry: { id: 5, name: 'Jane Doe' },
  campaign_id: 21,
  campaign: { id: 21, code: 'CMP-0021', name: 'Spring outreach' },
  lead_status_id: 7,
  lead_status: { id: 7, name: 'New', color: 'slate' },
  operational_site_id: null,
  operational_site: null,
  source_id: null,
  source: null,
  operator_id: null,
  operator: null,
  notes: null,
  extra_fields: null,
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
  permissions: {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: {},
    actions: {},
  },
}

const canMock = vi.fn<(permission: string) => boolean>()
// Default modal behaviour; force the resolved open mode (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

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

const refreshMock = vi.fn()
let capturedOnAction: RowActionHandler | null = null

const ROW: TableRow = { id: 33, actions: ['view', 'edit', 'delete'], name: 'Jane Doe' }

function action(key: string): TableActionDefinition {
  return {
    key,
    label: `actions.${key}`,
    icon: 'eye',
    type: key === 'delete' ? 'danger' : 'action',
    confirm: key === 'delete',
  }
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      capturedOnAction = onAction
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(action('view'), ROW)}>
            trigger-view
          </button>
          <button type="button" onClick={() => onAction(action('edit'), ROW)}>
            trigger-edit
          </button>
          <button type="button" onClick={() => onAction(action('delete'), ROW)}>
            trigger-delete
          </button>
        </div>
      )
    },
  ),
}))

const fetchLeadMock = vi.fn<() => Promise<LeadDetailWithPermissions>>()
const deleteLeadMock = vi.fn()

vi.mock('@/features/leads/api', () => ({
  fetchLead: () => fetchLeadMock(),
  deleteLead: (...args: unknown[]) => deleteLeadMock(...args),
  leadDetailQueryKey: (id: number | null) => ['leads', 'detail', id],
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/**
 * `LeadForm` submission is owned by a different lane and covered by its own
 * test suite. Here it is stubbed to two buttons that invoke
 * `onSuccess`/`onCancel` directly, so this suite can verify the table's own
 * responsibility: what happens to the Sheet and the grid once a create/edit
 * round-trips (AC-023).
 */
vi.mock('@/features/leads/lead-form', () => ({
  LeadForm: ({
    onSuccess,
    onCancel,
  }: {
    onSuccess: (lead: LeadDetailWithPermissions) => void
    onCancel: () => void
  }) => (
    <div>
      <button type="button" onClick={() => onSuccess(mockLead)}>
        stub-save
      </button>
      <button type="button" onClick={onCancel}>
        stub-cancel
      </button>
    </div>
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return {
    client,
    ...render(
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <LeadsTable />
        </MemoryRouter>
      </QueryClientProvider>,
    ),
  }
}

function axiosErrorWithStatus(status: number) {
  return new AxiosError('failed', String(status), undefined, undefined, {
    status,
    data: { success: false, message: 'error' },
  } as never)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  refreshMock.mockReset()
  capturedOnAction = null
  fetchLeadMock.mockReset()
  fetchLeadMock.mockResolvedValue(mockLead)
  deleteLeadMock.mockReset()
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('LeadsTable — Sheet-based CRUD (AC-024)', () => {
  it('mounts <TableView domain="leads">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-leads' })).toBeInTheDocument()
    expect(capturedOnAction).not.toBeNull()
  })

  it('opens the view sheet with the lead detail on the view action, without navigating', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-view'))

    await waitFor(() => expect(fetchLeadMock).toHaveBeenCalled())
    expect(await screen.findAllByText('Jane Doe')).not.toHaveLength(0)
  })

  it('opens the edit sheet on the edit action', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-edit'))

    expect(await screen.findByText('Edit lead')).toBeInTheDocument()
  })

  it('opens the create sheet from the New lead button', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: /new lead/i }))

    expect(await screen.findByText('Create lead')).toBeInTheDocument()
  })

  it('hides the New lead button without leads.create', () => {
    canMock.mockImplementation((permission) => permission !== 'leads.create')

    renderTable()

    expect(screen.queryByRole('button', { name: /new lead/i })).not.toBeInTheDocument()
  })

  it('deletes the row, refreshes the grid and shows a success toast', async () => {
    deleteLeadMock.mockResolvedValue(undefined)
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() => expect(deleteLeadMock).toHaveBeenCalledWith(33))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(toast.success).toHaveBeenCalledWith('Lead deleted successfully.')
  })

  it('maps a 403 delete failure to the forbidden message', async () => {
    deleteLeadMock.mockRejectedValue(axiosErrorWithStatus(403))
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('You cannot delete this lead.'),
    )
  })

  it('maps any other delete failure to the generic error message', async () => {
    deleteLeadMock.mockRejectedValue(axiosErrorWithStatus(500))
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to delete the lead. Please try again.'),
    )
  })
})

describe('LeadsTable — mutation success closes the sheet and refreshes (AC-023)', () => {
  it('closes the create sheet, refreshes the grid and invalidates the detail query on save', async () => {
    const { client } = renderTable()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    fireEvent.click(screen.getByRole('button', { name: /new lead/i }))
    expect(await screen.findByText('Create lead')).toBeInTheDocument()

    fireEvent.click(screen.getByText('stub-save'))

    await waitFor(() => expect(screen.queryByText('Create lead')).not.toBeInTheDocument())
    expect(refreshMock).toHaveBeenCalled()
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['leads', 'detail', 33] })
  })

  it('closes the edit sheet on cancel without refreshing the grid', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-edit'))
    expect(await screen.findByText('Edit lead')).toBeInTheDocument()

    fireEvent.click(await screen.findByText('stub-cancel'))

    await waitFor(() => expect(screen.queryByText('Edit lead')).not.toBeInTheDocument())
    expect(refreshMock).not.toHaveBeenCalled()
  })
})
