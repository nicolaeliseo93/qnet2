import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import type { TableRow } from '@/features/table/types'

/**
 * Spec 0034, AC-015: the row-action Dialog mounts the shared
 * `ActivityLogSection` for the row's resource/id when open, and renders
 * nothing when `row` is `null` (closed). Generic across every domain table —
 * this suite exercises it with an arbitrary `resource` prop, not just "users".
 */

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

const ROW: TableRow = { id: 7, actions: ['activity'], name: 'Acme rollout' }

function renderDialog(row: TableRow | null, onOpenChange = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ResourceActivityDialog resource="companies" row={row} onOpenChange={onOpenChange} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
})

describe('ResourceActivityDialog', () => {
  it('mounts the shared ActivityLogSection with the row id and given resource when open', () => {
    renderDialog(ROW)

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'companies', id: 7 })
  })

  it('renders closed and mounts nothing when row is null', () => {
    renderDialog(null)

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})
