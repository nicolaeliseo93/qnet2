/**
 * Notification bell strings. Sibling file so `en.ts` stays within the
 * engineering size limits (see `.claude/rules/engineering.md` §6).
 */

export const notifications = {
  title: 'Notifications',
  open: 'Open notifications',
  filterLabel: 'Filter notifications',
  filters: {
    all: 'All',
    unread: 'Unread',
    read: 'Read',
  },
  empty: 'You have no notifications.',
  untitled: 'Notification',
  markAllAsRead: 'Mark all as read',
  markAsRead: 'Mark as read',
  unreadCount: '{{count}} unread notifications',
  loadError: 'Unable to load notifications. Please try again.',
  actionError: 'Something went wrong. Please try again.',
}
