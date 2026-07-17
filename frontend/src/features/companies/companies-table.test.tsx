import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CompaniesTable } from '@/features/companies/companies-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

// This suite exercises the default modal behaviour; force the resolved open
// mode so it never depends on an AuthProvider (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

/**
 * Spec 0034, AC-015: representative module for the activity log rollout
 * beyond Users. The generic `<TableView>` is stubbed with a button that fires
 * `onAction('activity', ...)` for a fixed row, mirroring the sheet-based
 * adapters' suites (e.g. `ProjectsTable`).
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

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

const refreshMock = vi.fn()
const ROW: TableRow = { id: 3, actions: ['view', 'edit', 'delete', 'activity'], name: 'Acme S.p.A.' }

function action(key: string): TableActionDefinition {
  return { key, label: `actions.${key}`, icon: 'eye', type: key === 'delete' ? 'danger' : 'action', confirm: false }
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(action('activity'), ROW)}>
            trigger-activity
          </button>
        </div>
      )
    },
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <CompaniesTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  refreshMock.mockReset()
  activityLogSectionMock.mockReset()
})

describe('CompaniesTable — activity row action', () => {
  it('mounts <TableView domain="companies">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-companies' })).toBeInTheDocument()
  })

  it('opens the activity dialog for the row and mounts the shared ActivityLogSection', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-activity'))

    expect(await screen.findByRole('dialog')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'companies', id: 3 })
  })

  it('closes the dialog when it is dismissed', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-activity'))
    expect(await screen.findByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })
})
