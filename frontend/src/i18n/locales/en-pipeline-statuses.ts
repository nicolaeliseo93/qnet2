/**
 * Project statuses domain (spec 0023). Extracted to a sibling file to keep
 * `en.ts` within the engineering size limits (see
 * `.claude/rules/engineering.md` §6); mirrors `sources` (spec 0018) 1:1,
 * with the `color`/`sort_order` fields added.
 */

export const pipelineStatuses = {
  title: 'Project/Campaign statuses',
  subtitle: 'Browse, filter and manage the statuses used by projects and campaigns.',
  forbidden: "You don't have permission to view project statuses.",
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
    title: 'Project status details',
    subtitle: 'Read-only view of the selected project status.',
    loadError: 'Unable to load the project status. Please try again.',
    color: 'Color',
    sort_order: 'Order',
    created_at: 'Created at',
  },
  form: {
    newPipelineStatus: 'New status',
    createTitle: 'Create project status',
    createSubtitle: 'Add a new status for projects and campaigns.',
    editTitle: 'Edit project status',
    editSubtitle: 'Update the selected project status.',
    name: 'Name',
    color: 'Color',
    sortOrder: 'Order',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Project status created successfully.',
    updated: 'Project status updated successfully.',
    deleted: 'Project status deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    colorMax: 'Color must be at most 32 characters.',
    sortOrderInvalid: 'Order must be a whole number.',
    sortOrderMin: 'Order must be zero or greater.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the project status. Please try again.',
    deleteForbidden: 'You cannot delete this project status.',
    deleteInUseFallback: 'This status is used by a project or a campaign and cannot be deleted.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, color and sort order of the status.',
      },
    },
  },
}
