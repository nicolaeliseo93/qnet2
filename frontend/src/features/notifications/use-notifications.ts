import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { env } from '@/config/env'
import { fetchNotifications, fetchUnreadCount } from '@/features/notifications/api'
import { notificationKeys } from '@/features/notifications/query-keys'
import type { NotificationFilter } from '@/features/notifications/types'
import { useAuth } from '@/features/auth/use-auth'

/** Page size requested per infinite-scroll fetch. */
export const NOTIFICATIONS_PAGE_SIZE = 15

/**
 * Lightweight, always-on poll for the unread badge count. Only runs while the
 * user is authenticated and never refetches while the tab is in the background.
 */
export function useUnreadCount() {
  const { isAuthenticated } = useAuth()

  return useQuery({
    queryKey: notificationKeys.unreadCount,
    queryFn: fetchUnreadCount,
    enabled: isAuthenticated,
    refetchInterval: isAuthenticated ? env.notificationsPollInterval : false,
    refetchIntervalInBackground: false,
  })
}

/**
 * Infinite-scroll list for the panel. Only enabled while the panel is open (and
 * the user authenticated) to avoid useless calls; keeps polling while open so
 * the loaded pages stay fresh. The next page offset is derived from the backend
 * pagination envelope; `getNextPageParam` returns undefined once every row is
 * loaded, which sets `hasNextPage` to false.
 */
export function useNotificationList(open: boolean, filter: NotificationFilter) {
  const { isAuthenticated } = useAuth()

  return useInfiniteQuery({
    queryKey: notificationKeys.list(filter),
    queryFn: ({ pageParam }) =>
      fetchNotifications({
        filter,
        offset: pageParam,
        limit: NOTIFICATIONS_PAGE_SIZE,
      }),
    initialPageParam: 0,
    getNextPageParam: (lastPage) => {
      const { offset, limit, total } = lastPage.pagination
      const nextOffset = offset + limit
      return nextOffset < total ? nextOffset : undefined
    },
    enabled: isAuthenticated && open,
    refetchInterval: open ? env.notificationsPollInterval : false,
    refetchIntervalInBackground: false,
  })
}
