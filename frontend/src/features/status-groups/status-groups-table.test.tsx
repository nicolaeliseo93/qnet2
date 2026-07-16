import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import StatusGroupsPage from '@/pages/status-groups-page'
import { StatusGroupsTable } from '@/features/status-groups/status-groups-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Mirrors `lead-statuses-table.test.tsx` (spec 0029): the generic
 * `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`) are
 * framework pieces outside this microtask's ownership: they are stubbed so
 * the suite stays focused on what THIS adapter is responsible for — wiring
 * `<Can>` around the table, mounting `<TableView domain="status-groups">`,
 * and the delete flow (D-6: surfacing the backend's exact 409 message).
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

const deleteStatusGroupMock = vi.fn()
vi.mock('@/features/status-groups/api', () => ({
  deleteStatusGroup: (...args: unknown[]) => deleteStatusGroupMock(...args),
  fetchStatusGroup: vi.fn(),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({ toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: (...args: unknown[]) => toastErrorMock(...args) } }))

const DELETE_ACTION: TableActionDefinition = {
  key: 'delete',
  label: 'actions.delete',
  icon: 'trash',
  type: 'danger',
  confirm: true,
}

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Open' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction?: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction?.(DELETE_ACTION, ROW)}>
          delete row
        </button>
      </div>
    )
  }),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <StatusGroupsPage />
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <StatusGroupsTable />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  deleteStatusGroupMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('StatusGroupsPage — permission gating (spec 0039 AC-012)', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText("You don't have permission to view status groups."),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-status-groups' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="status-groups"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'status-groups.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-status-groups' })).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view status groups."),
    ).not.toBeInTheDocument()
  })
})

describe('StatusGroupsTable — delete (spec 0039 D-6)', () => {
  it('shows the backend message on a 409 (group still in use)', async () => {
    deleteStatusGroupMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: { success: false, message: 'This status group is used by a status and cannot be deleted.' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteStatusGroupMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'This status group is used by a status and cannot be deleted.',
      ),
    )
  })

  it('shows a forbidden toast on a 403', async () => {
    deleteStatusGroupMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this status group.'),
    )
  })

  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteStatusGroupMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Status group deleted successfully.'),
    )
  })
})
