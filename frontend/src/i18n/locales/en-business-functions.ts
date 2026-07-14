/**
 * Business Functions domain. Sibling file so `en.ts` stays within the
 * engineering size limits (see `.claude/rules/engineering.md` §6).
 */
import { moduleStats } from './en-stats'

export const businessFunctions = {
  stats: moduleStats.businessFunctions,
  title: 'Business Functions',
  subtitle: 'Browse, filter and manage the business functions of your organization.',
  forbidden: "You don't have permission to view business functions.",
  columns: {
    name: 'Name',
    is_business_unit: 'Business Unit',
    is_business_service: 'Business Service',
    manager: 'Responsible',
    users: 'Associated users',
    created_at: 'Created at',
  },
  detail: {
    title: 'Business function details',
    subtitle: 'Read-only view of the selected business function.',
    loadError: 'Unable to load the business function. Please try again.',
    name: 'Name',
    type: 'Type',
    manager: 'Responsible',
    users: 'Associated users',
    created_at: 'Created at',
  },
  form: {
    newBusinessFunction: 'New business function',
    createTitle: 'Create business function',
    createSubtitle: 'Add a new business function to your organization.',
    editTitle: 'Edit business function',
    editSubtitle: 'Update the selected business function.',
    name: 'Name',
    manager: 'Responsible',
    managerPlaceholder: 'Select a responsible…',
    users: 'Associated users',
    usersPlaceholder: 'Select users…',
    usersSearch: 'Search users…',
    usersEmpty: 'No users found.',
    usersError: 'Unable to load users.',
    usersRemove: 'Remove user',
    type: {
      label: 'Type',
      businessUnit: 'Business Unit',
      businessService: 'Business Service',
      none: 'None',
    },
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Business function created successfully.',
    updated: 'Business function updated successfully.',
    deleted: 'Business function deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the business function. Please try again.',
    deleteForbidden: 'You cannot delete this business function.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name and type of the business function.',
      },
      assignment: {
        title: 'Assignment',
        description: 'Responsible and associated users.',
      },
    },
  },
}
