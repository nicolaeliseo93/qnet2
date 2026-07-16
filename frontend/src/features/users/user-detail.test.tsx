import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { UserDetailView } from '@/features/users/user-detail'
import type { UserDetailWithPermissions } from '@/features/users/types'

/**
 * Spec 0034, AC-015: the "Activity log" DetailSection mounts only when the
 * detail envelope grants `permissions.actions.view_activity`, and — when
 * mounted — renders the shared `ActivityLogSection` (verified here by id and
 * resource props, its own rendering is covered by activity-log-section.test.tsx).
 */

const fetchUserMock = vi.fn()
const activityLogSectionMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  fetchUser: (...args: unknown[]) => fetchUserMock(...args),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function user(overrides: Partial<UserDetailWithPermissions> = {}): UserDetailWithPermissions {
  return {
    id: 1,
    name: 'Jane Doe',
    email: 'jane@example.com',
    locale: 'en',
    is_active: true,
    roles: [],
    avatar_url: null,
    created_at: null,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

function renderDetail(userDetail: UserDetailWithPermissions) {
  fetchUserMock.mockResolvedValue(userDetail)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <UserDetailView userId={userDetail.id} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchUserMock.mockReset()
  activityLogSectionMock.mockReset()
})

describe('UserDetailView — activity log section (AC-015)', () => {
  it('mounts the section for the viewed user when view_activity is granted', async () => {
    renderDetail(user({ permissions: { ...user().permissions, actions: { view_activity: true } } }))

    await waitFor(() => expect(screen.getByText('Jane Doe')).toBeInTheDocument())
    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'users', id: 1 })
  })

  it('hides the section when view_activity is not granted', async () => {
    renderDetail(user({ permissions: { ...user().permissions, actions: { view_activity: false } } }))

    await waitFor(() => expect(screen.getByText('Jane Doe')).toBeInTheDocument())
    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})
