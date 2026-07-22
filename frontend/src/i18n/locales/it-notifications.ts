/**
 * Stringhe della campanella notifiche. File satellite per mantenere `it.ts`
 * entro i limiti dimensionali (vedi `.claude/rules/engineering.md` §6).
 */

export const notifications = {
  title: 'Notifiche',
  open: 'Apri notifiche',
  filterLabel: 'Filtra notifiche',
  filters: {
    all: 'Tutte',
    unread: 'Non lette',
    read: 'Lette',
  },
  empty: 'Non hai notifiche.',
  untitled: 'Notifica',
  markAllAsRead: 'Segna tutte come lette',
  markAsRead: 'Segna come letta',
  unreadCount: '{{count}} notifiche non lette',
  loadError: 'Impossibile caricare le notifiche. Riprova.',
  actionError: 'Si è verificato un errore. Riprova.',
}
