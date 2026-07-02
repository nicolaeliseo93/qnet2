import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  Notification,
  NotificationFilter,
  PaginatedResponse,
} from '@/features/notifications/types'

interface FetchNotificationsParams {
  offset?: number
  limit?: number
  filter?: NotificationFilter
}

/**
 * Fetches a page of notifications. The list endpoint returns the paginated
 * envelope directly (not wrapped in the standard `ApiResponse`).
 */
export async function fetchNotifications(
  params: FetchNotificationsParams = {},
): Promise<PaginatedResponse<Notification>> {
  const { offset = 0, limit = 15, filter = 'all' } = params
  const { data } = await apiClient.get<PaginatedResponse<Notification>>(
    '/notifications',
    { params: { offset, limit, filter } },
  )
  return data
}

/** Fetches the count of unread notifications from the standard envelope. */
export async function fetchUnreadCount(): Promise<number> {
  const { data } = await apiClient.get<ApiResponse<{ count: number }>>(
    '/notifications/unread-count',
  )
  return data.data.count
}

/** Marks a single notification as read. Returns the updated notification. */
export async function markNotificationAsRead(id: string): Promise<Notification> {
  const { data } = await apiClient.patch<ApiResponse<Notification>>(
    `/notifications/${id}/read`,
  )
  return data.data
}

/** Marks all notifications as read. Returns the number marked. */
export async function markAllNotificationsAsRead(): Promise<number> {
  const { data } = await apiClient.post<ApiResponse<{ marked: number }>>(
    '/notifications/read-all',
  )
  return data.data.marked
}
