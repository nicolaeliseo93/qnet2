import { render, screen } from '@testing-library/react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { NotificationItem } from '@/features/notifications/notification-item'
import type { Notification } from '@/features/notifications/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function buildNotification(overrides: Partial<Notification> = {}): Notification {
  return {
    id: 'n1',
    type: 'generic',
    data: { title: 'Hello', message: 'World', level: 'info', action_url: null },
    read_at: null,
    created_at: '2026-06-15T10:00:00.000Z',
    ...overrides,
  }
}

describe('NotificationItem', () => {
  it('renders the title and message from the data payload', () => {
    render(
      <NotificationItem
        notification={buildNotification()}
        onMarkAsRead={vi.fn()}
      />,
    )
    expect(screen.getByText('Hello')).toBeInTheDocument()
    expect(screen.getByText('World')).toBeInTheDocument()
  })

  it('shows the mark-as-read action only while unread', () => {
    const { rerender } = render(
      <NotificationItem
        notification={buildNotification({ read_at: null })}
        onMarkAsRead={vi.fn()}
      />,
    )
    expect(
      screen.getByRole('button', { name: 'Mark as read' }),
    ).toBeInTheDocument()

    rerender(
      <NotificationItem
        notification={buildNotification({ read_at: '2026-06-15T11:00:00.000Z' })}
        onMarkAsRead={vi.fn()}
      />,
    )
    expect(
      screen.queryByRole('button', { name: 'Mark as read' }),
    ).not.toBeInTheDocument()
  })
})
