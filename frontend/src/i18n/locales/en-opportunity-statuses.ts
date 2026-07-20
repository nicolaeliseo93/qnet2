/**
 * Opportunity statuses domain (spec 0043). Sibling file so `en.ts` stays
 * within the engineering size limits (see `.claude/rules/engineering.md`
 * §6); mirrors `lead-statuses` (spec 0029 + 0039) 1:1, with the delete-guard
 * message adjusted to Opportunities (BR-4). System statuses are
 * "Nuova"/"Chiusa con successo"/"Persa" and a fixed 3-value `group` enum
 * (open/pending/closed), plus a drag & drop reorder sheet for the custom
 * rows.
 */

export const opportunityStatuses = {
  title: 'Opportunity statuses',
  subtitle: 'Browse, filter and manage the statuses used by opportunities.',
  forbidden: "You don't have permission to view opportunity statuses.",
  columns: {
    name: 'Name',
    color: 'Color',
    sort_order: 'Order',
    group: 'Group',
    created_at: 'Created at',
  },
  advancedFilters: {
    name: 'Name',
    sortOrderRange: 'Order',
    createdRange: 'Created at',
  },
  detail: {
    title: 'Opportunity status details',
    subtitle: 'Read-only view of the selected opportunity status.',
    loadError: 'Unable to load the opportunity status. Please try again.',
    color: 'Color',
    sort_order: 'Order',
    group: 'Group',
    created_at: 'Created at',
  },
  form: {
    newOpportunityStatus: 'New status',
    createTitle: 'Create opportunity status',
    createSubtitle: 'Add a new status for opportunities.',
    editTitle: 'Edit opportunity status',
    editSubtitle: 'Update the selected opportunity status.',
    name: 'Name',
    color: 'Color',
    group: {
      label: 'Group',
      open: 'Open',
      pending: 'Pending',
      closed: 'Closed',
    },
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Opportunity status created successfully.',
    updated: 'Opportunity status updated successfully.',
    deleted: 'Opportunity status deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    colorMax: 'Color must be at most 32 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the opportunity status. Please try again.',
    deleteForbidden: 'You cannot delete this opportunity status.',
    deleteInUseFallback: 'This opportunity status is used by an opportunity and cannot be deleted.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, color and group of the status.',
      },
    },
    hints: {
      systemStatusGroup: "A system status's group is fixed and cannot be changed.",
    },
  },
  reorder: {
    openButton: 'Reorder',
    title: 'Reorder statuses',
    subtitle: 'Drag the custom statuses to reorder them. "Nuova" stays first and "Persa" stays last.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the statuses. Please try again.',
    saved: 'Order updated successfully.',
    forbidden: 'You cannot reorder these statuses.',
    genericError: 'Unable to update the order. Please try again.',
  },
}
