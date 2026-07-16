/**
 * Localized strings for the generic aggregated activity log feature
 * (spec 0034): the timeline section shown in a resource's detail and in its
 * row-action Dialog. `fields.*` intentionally has no entries yet (v1) —
 * `ActivityLogSection` falls back to the raw field key when a translation is
 * missing, so untranslated fields still render.
 */
export const activityLog = {
  title: 'Activity log',
  loadError: 'Unable to load the activity log. Please try again.',
  empty: 'No activity recorded yet.',
  loadMore: 'Load more',
  systemCauser: 'System',
  events: {
    created: 'Created',
    updated: 'Updated',
    deleted: 'Deleted',
    restored: 'Restored',
  },
  modules: {
    user: 'User',
    personal_data: 'Personal data',
    contact: 'Contact',
    address: 'Address',
  },
}
