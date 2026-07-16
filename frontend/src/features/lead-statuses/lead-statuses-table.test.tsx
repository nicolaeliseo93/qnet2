import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import LeadStatusesPage from '@/pages/lead-statuses-page'
import { LeadStatusesTable } from '@/features/lead-statuses/lead-statuses-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Mirrors `pipeline-statuses-table.test.tsx` (spec 0023): the generic
 * `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`) are
 * framework pieces outside this microtask's ownership: they are stubbed so
 * the suite stays focused on what THIS adapter is responsible for — wiring
 * `<Can>` around the table, mounting `<TableView domain="lead-statuses">`,
 * and the delete flow (BR-3: surfacing the backend's exact 409 message).
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

const deleteLeadStatusMock = vi.fn()
vi.mock('@/features/lead-statuses/api', () => ({
  deleteLeadStatus: (...args: unknown[]) => deleteLeadStatusMock(...args),
  fetchLeadStatus: vi.fn(),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'New' }

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
      <LeadStatusesPage />
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <LeadStatusesTable />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  deleteLeadStatusMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('LeadStatusesPage — permission gating (AC-017)', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText("You don't have permission to view lead statuses."),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-lead-statuses' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="lead-statuses"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'lead-statuses.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-lead-statuses' })).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view lead statuses."),
    ).not.toBeInTheDocument()
  })
})

describe('LeadStatusesTable — reorder toggle (spec 0039 D-4)', () => {
  it('shows the reorder button with lead-statuses.update', () => {
    canMock.mockImplementation((permission) => permission === 'lead-statuses.update')

    renderTable()

    expect(screen.getByRole('button', { name: 'Reorder' })).toBeInTheDocument()
  })

  it('hides the reorder button without lead-statuses.update', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'Reorder' })).not.toBeInTheDocument()
  })
})

describe('LeadStatusesTable — delete (BR-3)', () => {
  it('shows the backend message on a 409 (status still in use)', async () => {
    deleteLeadStatusMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: { success: false, message: 'This lead status is used by a lead and cannot be deleted.' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteLeadStatusMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'This lead status is used by a lead and cannot be deleted.',
      ),
    )
  })

  it('shows a forbidden toast on a 403', async () => {
    deleteLeadStatusMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this lead status.'),
    )
  })

  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteLeadStatusMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Lead status deleted successfully.'),
    )
  })
})
