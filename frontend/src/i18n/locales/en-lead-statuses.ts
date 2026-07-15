/**
 * Lead statuses domain (spec 0029). Extracted to a sibling file to keep
 * `en.ts` within the engineering size limits (see
 * `.claude/rules/engineering.md` §6); mirrors `pipeline-statuses` (spec 0023)
 * 1:1, with the delete-guard message adjusted to Leads (BR-3).
 */

export const leadStatuses = {
  title: 'Lead statuses',
  subtitle: 'Browse, filter and manage the statuses used by leads.',
  forbidden: "You don't have permission to view lead statuses.",
  columns: {
    name: 'Name',
    color: 'Color',
    sort_order: 'Order',
    created_at: 'Created at',
  },
  detail: {
    title: 'Lead status details',
    subtitle: 'Read-only view of the selected lead status.',
    loadError: 'Unable to load the lead status. Please try again.',
    color: 'Color',
    sort_order: 'Order',
    created_at: 'Created at',
  },
  form: {
    newLeadStatus: 'New status',
    createTitle: 'Create lead status',
    createSubtitle: 'Add a new status for leads.',
    editTitle: 'Edit lead status',
    editSubtitle: 'Update the selected lead status.',
    name: 'Name',
    color: 'Color',
    sortOrder: 'Order',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Lead status created successfully.',
    updated: 'Lead status updated successfully.',
    deleted: 'Lead status deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    colorMax: 'Color must be at most 32 characters.',
    sortOrderInvalid: 'Order must be a whole number.',
    sortOrderMin: 'Order must be zero or greater.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the lead status. Please try again.',
    deleteForbidden: 'You cannot delete this lead status.',
    deleteInUseFallback: 'This lead status is used by a lead and cannot be deleted.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, color and sort order of the status.',
      },
    },
  },
}
