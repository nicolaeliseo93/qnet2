import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { ActivityLogEntry, ActivityLogPage } from '@/features/activity-log/types'

/**
 * Spec 0034, AC-014: the timeline shows date/time, author, operation type,
 * module and the field-level diff for every entry; loads more pages on
 * demand; covers the loading/empty/error states.
 */

const fetchActivityLogMock = vi.fn()

vi.mock('@/features/activity-log/api', () => ({
  ACTIVITY_LOG_DEFAULT_PAGE_SIZE: 25,
  fetchActivityLog: (...args: unknown[]) => fetchActivityLogMock(...args),
}))

function entry(overrides: Partial<ActivityLogEntry> = {}): ActivityLogEntry {
  return {
    id: 1,
    logged_at: '2026-07-16T10:30:00.000Z',
    event: 'updated',
    module: 'user',
    subject_id: 1,
    causer: { id: 9, name: 'Jane Doe' },
    changes: [{ field: 'email', old_value: 'old@example.com', new_value: 'new@example.com' }],
    ...overrides,
  }
}

function page(overrides: Partial<ActivityLogPage> = {}): ActivityLogPage {
  return { items: [entry()], next_cursor: null, ...overrides }
}

function renderSection() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ActivityLogSection resource="users" id={1} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchActivityLogMock.mockReset()
})

describe('ActivityLogSection', () => {
  it('renders logged_at, causer, event, module and the field diff for each entry', async () => {
    fetchActivityLogMock.mockResolvedValue(page())

    renderSection()

    await waitFor(() => expect(screen.getByText('Jane Doe')).toBeInTheDocument())
    expect(screen.getByText(/Updated/)).toBeInTheDocument()
    expect(screen.getByText(/User/)).toBeInTheDocument()
    expect(screen.getByText(/email/)).toBeInTheDocument()
    expect(screen.getByText(/old@example.com/)).toBeInTheDocument()
    expect(screen.getByText(/new@example.com/)).toBeInTheDocument()
  })

  it('falls back to the system-causer label when causer is null', async () => {
    fetchActivityLogMock.mockResolvedValue(
      page({ items: [entry({ causer: { id: null, name: null } })] }),
    )

    renderSection()

    await waitFor(() => expect(screen.getByText('System')).toBeInTheDocument())
  })

  it('shows the empty state when there are no entries', async () => {
    fetchActivityLogMock.mockResolvedValue(page({ items: [] }))

    renderSection()

    await waitFor(() =>
      expect(screen.getByText('No activity recorded yet.')).toBeInTheDocument(),
    )
  })

  it('shows the error state with a retry action', async () => {
    fetchActivityLogMock.mockRejectedValue(new Error('network error'))

    renderSection()

    await waitFor(() =>
      expect(
        screen.getByText('Unable to load the activity log. Please try again.'),
      ).toBeInTheDocument(),
    )
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('requests the next page when "Load more" is clicked', async () => {
    fetchActivityLogMock.mockResolvedValueOnce(
      page({ items: [entry({ id: 1 })], next_cursor: 'cursor-2' }),
    )
    fetchActivityLogMock.mockResolvedValueOnce(
      page({ items: [entry({ id: 2, causer: { id: null, name: null } })], next_cursor: null }),
    )

    renderSection()

    const loadMore = await screen.findByRole('button', { name: 'Load more' })
    loadMore.click()

    await waitFor(() => expect(fetchActivityLogMock).toHaveBeenCalledTimes(2))
    expect(fetchActivityLogMock).toHaveBeenLastCalledWith('users', 1, 'cursor-2', 25)
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'Load more' })).not.toBeInTheDocument(),
    )
  })
})
