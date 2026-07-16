import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import TagsPage from '@/pages/tags-page'
import { TagsTable } from '@/features/tags/tags-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Permission gating of the Tags page (spec 0019, mirrors
 * `referent-types-table.test.tsx`). The generic `<TableView>` (AG Grid +
 * SSRM) and the app chrome (`PageHeader`) are framework pieces outside this
 * module's ownership: they are stubbed so the suite stays focused on what
 * THIS adapter is responsible for — wiring `<Can>` around the table and
 * mounting `<TableView domain="tags">` with the right domain.
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

const ROW: TableRow = { id: 5, actions: ['activity'], name: 'Priority' }

function action(key: string): TableActionDefinition {
  return { key, label: `actions.${key}`, icon: 'eye', type: 'action', confirm: false }
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction?: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: () => {} }))
      return (
        <div role="region" aria-label={`table-${domain}`}>
          {onAction ? (
            <button type="button" onClick={() => onAction(action('activity'), ROW)}>
              trigger-activity
            </button>
          ) : null}
        </div>
      )
    },
  ),
}))

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <TagsPage />
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <TagsTable />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  activityLogSectionMock.mockReset()
})

describe('TagsPage — permission gating', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(screen.getByText("You don't have permission to view tags.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-tags' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="tags"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'tags.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-tags' })).toBeInTheDocument()
    expect(screen.queryByText("You don't have permission to view tags.")).not.toBeInTheDocument()
  })
})

/** Spec 0034, AC-015: representative module for the activity log rollout beyond Users. */
describe('TagsTable — activity row action', () => {
  it('opens the activity dialog for the row and mounts the shared ActivityLogSection', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-activity'))

    expect(await screen.findByRole('dialog')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'tags', id: 5 })
  })

  it('closes the dialog when it is dismissed', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-activity'))
    expect(await screen.findByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })
})
