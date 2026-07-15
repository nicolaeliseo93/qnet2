/**
 * Localized strings for the advanced lead import wizard (spec 0033): upload,
 * global configuration, column mapping, review and summary steps.
 *
 * Extracted into its own module (registered as its own i18next namespace, see
 * `features/imports/wizard/i18n.ts`) to keep `en.ts` within the engineering
 * size limits (`.claude/rules/engineering.md` §6), mirroring the `migrations`
 * module's convention.
 */
export const importWizard = {
  nav: {
    label: 'Import',
  },
  page: {
    title: 'Import leads',
    subtitle: 'Upload a file to bulk-import leads through a guided, staged wizard.',
    forbidden: "You don't have permission to import leads.",
    loadError: 'Unable to load this import. Please try again.',
  },
  stepper: {
    upload: 'Upload',
    config: 'Configuration',
    mapping: 'Mapping',
    review: 'Review',
    summary: 'Summary',
  },
  upload: {
    fileLabel: 'File (.csv, .xlsx)',
    submit: 'Analyze file',
    uploading: 'Uploading…',
    analyzing: 'Analyzing the file…',
    summaryTitle: 'File analysis',
    columnsLabel: 'Columns detected',
    rowsLabel: 'Rows detected',
    duplicateColumnsLabel: 'Duplicate column names',
    noDuplicateColumns: 'None',
    continue: 'Continue to configuration',
    errors: {
      fileRequired: 'Select a file to upload.',
      fileType: 'Only .csv and .xlsx files are supported.',
    },
  },
  config: {
    title: 'Global configuration',
    subtitle: 'These values apply to every imported lead.',
    continue: 'Continue to mapping',
    back: 'Back',
    errors: {
      required: 'This field is required.',
    },
    select: {
      placeholder: 'Select…',
      searchPlaceholder: 'Search…',
      empty: 'No results.',
      error: 'Unable to load options.',
      clear: 'Clear selection',
      retry: 'Retry',
    },
  },
  mapping: {
    title: 'Column mapping',
    subtitle: 'Match each file column to a lead field, or ignore it.',
    targetHeader: 'Target field',
    ignore: 'Ignore this column',
    extra: 'Extra field',
    duplicateStrategy: 'Duplicate handling',
    submit: 'Save mapping and continue',
    submitting: 'Saving…',
    badges: {
      requiredMissing: 'Required fields not mapped: {{fields}}',
      duplicateColumn: 'Duplicate column name',
      conflict: 'Mapped more than once',
    },
    dedupModes: {
      create_new: 'Always create a new lead',
      update_existing: 'Update the matching referent',
      ignore: 'Skip matching rows',
      manual: 'Decide during review',
    },
    errors: {
      dedupRequired: 'Select a duplicate handling strategy.',
    },
  },
  review: {
    title: 'Review',
    placeholder: 'The row-by-row review grid is not available yet.',
    continue: 'Continue to summary',
    loading: 'Loading the review…',
    gridLabel: 'Staged rows review grid',
    needsAttention: 'Some rows need attention',
    editedTitle: 'Edited',
    columns: {
      rowNumber: '#',
      status: 'Status',
      messages: 'Messages',
      extraSuffix: 'extra',
    },
    status: {
      valid: 'Valid',
      warning: 'Warning',
      error: 'Error',
      duplicate: 'Duplicate',
      skipped: 'Skipped',
    },
    counts: {
      total: 'Total',
      valid: 'Valid',
      warning: 'Warnings',
      error: 'Errors',
      duplicate: 'Duplicates',
      modified: 'Modified',
    },
  },
  summary: {
    title: 'Summary',
    placeholder: 'The import summary is not available yet.',
    statusLabel: 'Status: {{status}}',
  },
  status: {
    analyzing: 'Analyzing',
    configuring: 'Configuring',
    staging: 'Applying mapping…',
    reviewing: 'Reviewing',
    processing: 'Importing…',
    completed: 'Completed',
    failed: 'Failed',
  },
  errors: {
    forbidden: "You don't have permission to import leads.",
    notFound: 'This import run does not exist.',
    validation: 'Some values are not valid. Please check and try again.',
    invalidState: 'This action is not available at this step.',
    generic: 'Something went wrong. Please try again.',
  },
}
