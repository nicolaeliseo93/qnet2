/**
 * Tags domain (spec 0019). Extracted to a sibling file to keep `en.ts`
 * within the engineering size limits (see `.claude/rules/engineering.md`
 * §6); mirrors `sources`/`referentTypes` 1:1, plus the delete-in-use (409)
 * branch mirroring `productCategories`.
 */

export const tags = {
  title: 'Tags',
  subtitle: 'Browse, filter and manage the tags of your organization.',
  forbidden: "You don't have permission to view tags.",
  columns: {
    name: 'Name',
    created_at: 'Created at',
  },
  detail: {
    title: 'Tag details',
    subtitle: 'Read-only view of the selected tag.',
    loadError: 'Unable to load the tag. Please try again.',
    created_at: 'Created at',
  },
  form: {
    newTag: 'New tag',
    createTitle: 'Create tag',
    createSubtitle: 'Add a new tag to your organization.',
    editTitle: 'Edit tag',
    editSubtitle: 'Update the selected tag.',
    name: 'Name',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Tag created successfully.',
    updated: 'Tag updated successfully.',
    deleted: 'Tag deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the tag. Please try again.',
    deleteForbidden: 'You cannot delete this tag.',
    deleteInUse: 'Cannot delete: the tag is attached to at least one record.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name of the tag.',
      },
    },
  },
}
