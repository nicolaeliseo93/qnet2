import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { OpenMode } from '@/features/modules/types'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * Spec 0049 AC-060: the `request-management` adapter mounts `<TableView>`,
 * wires the "Lavora" (`view`) row action through `useModuleOpener`
 * (resolved open-mode: modal Sheet vs `/request-management/:id` page), and
 * exposes no delete/edit affordance — the record IS the Opportunity (D-9/
 * D-10), no CRUD lives on this module. The generic `<TableView>` (AG Grid +
 * SSRM) is stubbed with buttons that fire `onAction` for a fixed row,
 * mirroring the other Sheet-based adapters' suites (e.g. `LeadsTable`). The
 * adapter's other row action (`documents`) has its own suite,
 * `request-management-table-documents.test.tsx`.
 */

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

let requestManagementOpenMode: OpenMode = 'page'
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => requestManagementOpenMode,
}))

const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const ROW: TableRow = { id: 7, actions: ['view'], name: 'Enterprise deal' }

function action(key: string): TableActionDefinition {
  return { key, label: `actions.${key}`, icon: 'eye', type: 'link', confirm: false }
}

let capturedOnAction: RowActionHandler | null = null

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: () => {} }))
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

function panel(): RequestWorkPanelWithPermissions {
  return {
    id: 7,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: null,
    commercial: null,
    opportunity_status: { id: 5, name: 'New', color: 'slate' },
    workflow_status: { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
    workflow_statuses: [{ id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false }],
    product_lines: [],
    client_identity: null,
    client_contacts: { owner: null, items: [] },
    client_address: null,
    referent_contacts: { owner: null, items: [] },
    applicable_attributes: [],
    attribute_values: {},
    next_callback_at: null,
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    permissions: {
      resource: { view: true, create: false, update: true, delete: false, export: false, import: false },
      fields: {},
      actions: {},
    },
  }
}

const fetchRequestWorkPanelMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: vi.fn(),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      {/* The work panel's anagraphic section mounts `ContactsManager`, whose
          delete flow needs the app-level confirm dialog. */}
      <ConfirmDialogProvider>
        <MemoryRouter>
          <RequestManagementTable />
        </MemoryRouter>
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  requestManagementOpenMode = 'page'
  navigateMock.mockReset()
  capturedOnAction = null
  fetchRequestWorkPanelMock.mockReset()
  fetchRequestWorkPanelMock.mockResolvedValue(panel())
})

describe('RequestManagementTable (spec 0049 AC-060)', () => {
  it('mounts <TableView domain="request-management">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-request-management' })).toBeInTheDocument()
    expect(capturedOnAction).not.toBeNull()
  })

  it('navigates to the deep-link page on the view action in page mode', async () => {
    requestManagementOpenMode = 'page'
    renderTable()

    fireEvent.click(screen.getByText('trigger-view'))

    await waitFor(() => expect(navigateMock).toHaveBeenCalledWith('/request-management/7'))
    expect(fetchRequestWorkPanelMock).not.toHaveBeenCalled()
  })

  it('opens the modal Sheet with the work panel on the view action in modal mode, without navigating', async () => {
    requestManagementOpenMode = 'modal'
    renderTable()

    fireEvent.click(screen.getByText('trigger-view'))

    await waitFor(() => expect(fetchRequestWorkPanelMock).toHaveBeenCalledWith(7))
    expect(await screen.findAllByText('Enterprise deal')).not.toHaveLength(0)
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('ignores non-view action keys: no edit/delete affordance', () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-edit'))
    fireEvent.click(screen.getByText('trigger-delete'))

    expect(navigateMock).not.toHaveBeenCalled()
    expect(fetchRequestWorkPanelMock).not.toHaveBeenCalled()
    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument()
  })
})
