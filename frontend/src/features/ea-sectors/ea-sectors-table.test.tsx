import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { EaSectorsTable } from '@/features/ea-sectors/ea-sectors-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { EaSectorDetailWithPermissions } from '@/features/ea-sectors/types'

/**
 * Spec 0018 AC-020 — the EA Sectors adapter mounts the generic table on the
 * `ea-sectors` domain and owns the view/edit/delete row-action wiring,
 * including mapping the delete endpoint's 403/409 to explicit messages. The
 * generic `<TableView>` (AG Grid + SSRM) is outside this adapter's ownership
 * and is stubbed with buttons that fire `onAction` for a fixed row, mirroring
 * the permission-gating suites' stub but extended to drive the row actions.
 */

const canMock = vi.fn<(permission: string) => boolean>()
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

const ROW: TableRow = { id: 7, actions: ['view', 'edit', 'delete'], name: 'Applications' }

function action(key: string): TableActionDefinition {
  return { key, label: `actions.${key}`, icon: 'eye', type: key === 'delete' ? 'danger' : 'action' }
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

const fetchEaSectorMock = vi.fn<() => Promise<EaSectorDetailWithPermissions>>()
const deleteEaSectorMock = vi.fn()
const fetchEaSectorTreeMock = vi.fn()

vi.mock('@/features/ea-sectors/api', () => ({
  fetchEaSector: () => fetchEaSectorMock(),
  deleteEaSector: (...args: unknown[]) => deleteEaSectorMock(...args),
  fetchEaSectorTree: () => fetchEaSectorTreeMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_ACCESS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function sector(): EaSectorDetailWithPermissions {
  return {
    id: 7,
    name: 'Applications',
    parent_id: null,
    parent: null,
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS,
  }
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <EaSectorsTable />
    </QueryClientProvider>,
  )
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
  fetchEaSectorMock.mockReset()
  fetchEaSectorMock.mockResolvedValue(sector())
  deleteEaSectorMock.mockReset()
  fetchEaSectorTreeMock.mockReset()
  fetchEaSectorTreeMock.mockResolvedValue([])
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('EaSectorsTable — adapter (AC-020)', () => {
  it('mounts <TableView domain="ea-sectors">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-ea-sectors' })).toBeInTheDocument()
    expect(capturedOnAction).not.toBeNull()
  })

  it('opens the view sheet with the sector detail on the view action', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-view'))

    await waitFor(() => expect(fetchEaSectorMock).toHaveBeenCalled())
    expect(await screen.findAllByText('Applications')).not.toHaveLength(0)
  })

  it('opens the edit sheet on the edit action', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-edit'))

    expect(await screen.findByText('Edit sector')).toBeInTheDocument()
  })

  it('deletes the row, refreshes the grid and shows a success toast', async () => {
    deleteEaSectorMock.mockResolvedValue(undefined)
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() => expect(deleteEaSectorMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(toast.success).toHaveBeenCalledWith('Sector deleted successfully.')
  })

  it('maps a 409 delete failure to the "has sub-sectors" message', async () => {
    deleteEaSectorMock.mockRejectedValue(axiosErrorWithStatus(409))
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('This sector has sub-sectors and cannot be deleted.'),
    )
  })

  it('maps a 403 delete failure to the forbidden message', async () => {
    deleteEaSectorMock.mockRejectedValue(axiosErrorWithStatus(403))
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('You cannot delete this sector.'),
    )
  })

  it('maps any other delete failure to the generic error message', async () => {
    deleteEaSectorMock.mockRejectedValue(axiosErrorWithStatus(500))
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to delete the sector. Please try again.'),
    )
  })
})
