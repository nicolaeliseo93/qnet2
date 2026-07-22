/**
 * Request Management domain (spec 0049): the "Gestione Richieste" operative
 * view over Opportunities for commercial operators (D-1, no new entity —
 * the record IS an Opportunity). Sibling file so `en.ts` stays within the
 * engineering size limits (see `.claude/rules/engineering.md` §6).
 */

export const requestManagement = {
  title: 'Request Management',
  subtitle: 'Work opportunities: verify contacts, complete dynamic fields and advance the working status.',
  forbidden: "You don't have permission to view Request Management.",
  columns: {
    productCategory: 'Product category',
    operator: 'Operator (GA2)',
    workflowStatus: 'Working status',
    firstName: 'First name',
    lastName: 'Last name',
    taxCode: 'Tax code',
    phone: 'Phone',
    updatedAt: 'Updated at',
    nextCallbackAt: 'Next callback',
  },
  advancedFilters: {
    registry: 'Registry',
    referent: 'Contact',
    workflowStatus: 'Working status',
    opportunityStatus: 'Sales status',
    expectedCloseRange: 'Expected close date',
    nextCallbackRange: 'Next callback',
  },
  detail: {
    title: 'Request details',
    subtitle: 'Work the selected opportunity: contacts, dynamic fields and working status.',
  },
  form: {
    /** `FormScreen` only exists to satisfy `ModuleRegistryEntry` (spec 0049 D-9): no create/edit route is ever generated (`generateRoutes: false`) and the table never calls `openCreate`/`openEdit`. */
    notApplicable: 'Request Management has no create or edit form: work the record from its detail panel.',
  },
  workPanel: {
    loadError: 'Could not load the record.',
    saving: 'Saving…',
    save: 'Save',
    saved: 'Working data saved.',
    genericError: 'Something went wrong. Please try again.',
    dynamicFields: {
      title: 'Additional information',
      empty: 'No additional fields for this opportunity.',
    },
    workflowStatus: {
      title: 'Working status',
      sectionDescription: 'Advance the working state of the request.',
      label: 'Working status',
      placeholder: 'Select a status',
      noteLabel: 'Note',
      notePlaceholder: 'Explain the reason for this change…',
    },
    callback: {
      title: 'Next callback',
      description: 'Plan the next follow-up call with the client.',
      label: 'Callback date and time',
      placeholder: 'Select date and time',
    },
    attribution: {
      title: 'Attribution',
      description: 'Where the request comes from and who is working on it.',
      source: 'Source',
      sourceSearch: 'Search a source',
      reporter: 'Reporter',
      reporterSearch: 'Search a reporter',
      operator: 'Operator (GA2)',
      operatorSearch: 'Search an operator',
      selectPlaceholder: 'Select',
      selectEmpty: 'No results',
      selectError: 'Could not load the options.',
    },
    client: {
      title: 'Client details',
      description: 'Identity, contacts and address of the client.',
      identityGroup: 'Identity',
      contactsGroup: 'Contacts',
      addressGroup: 'Address',
      addressEmpty: 'Not set',
    },
    header: {
      unsavedChanges: 'Unsaved changes',
      salesStatus: 'Sales',
      workingStatus: 'Working',
      nextCallback: 'Next callback',
    },
    summary: {
      title: 'Request summary',
      description: 'Read-only commercial context.',
      registry: 'Client',
      referent: 'Contact',
      commercial: 'Sales rep',
      expectedCloseDate: 'Expected close date',
      estimatedValue: 'Estimated value',
      successProbability: 'Success probability',
      productLines: 'Product lines',
    },
    collaboration: {
      notesTab: 'Notes',
      documentsTab: 'Documents',
      activityTab: 'History',
    },
    validation: {
      enumInvalid: 'Select a valid option.',
      required: 'This field is required.',
      noteRequired: 'A note is required to move to this status.',
    },
  },
}
