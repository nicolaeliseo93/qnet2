/**
 * Status groups domain (spec 0039). Extracted to a sibling file to keep
 * `en.ts` within the engineering size limits (see
 * `.claude/rules/engineering.md` §6); mirrors `lead-statuses` (spec 0029)
 * 1:1, with the delete-guard message adjusted to statuses (D-6).
 */

export const statusGroups = {
  title: 'Status groups',
  subtitle: 'Browse, filter and manage the groups used to classify statuses.',
  forbidden: "You don't have permission to view status groups.",
  columns: {
    name: 'Name',
    color: 'Color',
    sort_order: 'Order',
    created_at: 'Created at',
  },
  advancedFilters: {
    name: 'Name',
    sortOrderRange: 'Order',
    createdRange: 'Created at',
  },
  detail: {
    title: 'Status group details',
    subtitle: 'Read-only view of the selected status group.',
    loadError: 'Unable to load the status group. Please try again.',
    color: 'Color',
    sort_order: 'Order',
    created_at: 'Created at',
  },
  form: {
    newStatusGroup: 'New status group',
    createTitle: 'Create status group',
    createSubtitle: 'Add a new group to classify statuses.',
    editTitle: 'Edit status group',
    editSubtitle: 'Update the selected status group.',
    name: 'Name',
    color: 'Color',
    sortOrder: 'Order',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Status group created successfully.',
    updated: 'Status group updated successfully.',
    deleted: 'Status group deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    colorMax: 'Color must be at most 32 characters.',
    sortOrderInvalid: 'Order must be a whole number.',
    sortOrderMin: 'Order must be zero or greater.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the status group. Please try again.',
    deleteForbidden: 'You cannot delete this status group.',
    deleteInUseFallback: 'This status group is used by a status and cannot be deleted.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, color and sort order of the group.',
      },
    },
  },
}
