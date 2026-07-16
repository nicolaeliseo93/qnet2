/**
 * Lead statuses domain (spec 0029). Extracted to a sibling file to keep
 * `en.ts` within the engineering size limits (see
 * `.claude/rules/engineering.md` §6); mirrors `pipeline-statuses` (spec 0023)
 * 1:1, with the delete-guard message adjusted to Leads (BR-3). Spec 0039
 * extends it with system statuses ("Nuovo"/"Chiuso"), a `status_group`
 * classification and a drag & drop reorder sheet for the custom rows.
 */

export const leadStatuses = {
  title: 'Lead statuses',
  subtitle: 'Browse, filter and manage the statuses used by leads.',
  forbidden: "You don't have permission to view lead statuses.",
  columns: {
    name: 'Name',
    color: 'Color',
    sort_order: 'Order',
    status_group: 'Group',
    created_at: 'Created at',
  },
  advancedFilters: {
    name: 'Name',
    sortOrderRange: 'Order',
    statusGroup: 'Group',
    createdRange: 'Created at',
  },
  detail: {
    title: 'Lead status details',
    subtitle: 'Read-only view of the selected lead status.',
    loadError: 'Unable to load the lead status. Please try again.',
    color: 'Color',
    sort_order: 'Order',
    status_group: 'Group',
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
    statusGroup: 'Group',
    statusGroupSearch: 'Search groups…',
    selectPlaceholder: 'Select…',
    selectEmpty: 'No results found.',
    selectError: 'Unable to load the options.',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Lead status created successfully.',
    updated: 'Lead status updated successfully.',
    deleted: 'Lead status deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    colorMax: 'Color must be at most 32 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the lead status. Please try again.',
    deleteForbidden: 'You cannot delete this lead status.',
    deleteInUseFallback: 'This lead status is used by a lead and cannot be deleted.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, color and group of the status.',
      },
    },
    hints: {
      systemStatusGroup: 'System statuses have a fixed group and cannot be reclassified.',
    },
  },
  reorder: {
    openButton: 'Reorder',
    title: 'Reorder statuses',
    subtitle: 'Drag the custom statuses to reorder them. "Nuovo" stays first and "Chiuso" stays last.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the statuses. Please try again.',
    saved: 'Order updated successfully.',
    forbidden: 'You cannot reorder these statuses.',
    genericError: 'Unable to update the order. Please try again.',
  },
}
