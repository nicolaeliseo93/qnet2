import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  markAllNotificationsAsRead,
  markNotificationAsRead,
} from '@/features/notifications/api'
import { notificationKeys } from '@/features/notifications/query-keys'

/**
 * Mutations for marking notifications as read. On success both the list and the
 * unread count are invalidated (via the shared `all` key) so the UI refetches.
 * Errors surface as a toast using i18n keys.
 */
export function useNotificationActions() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: notificationKeys.all })

  const markAsRead = useMutation({
    mutationFn: (id: string) => markNotificationAsRead(id),
    onSuccess: invalidate,
    onError: () => toast.error(t('notifications.actionError')),
  })

  const markAllAsRead = useMutation({
    mutationFn: () => markAllNotificationsAsRead(),
    onSuccess: invalidate,
    onError: () => toast.error(t('notifications.actionError')),
  })

  return { markAsRead, markAllAsRead }
}
