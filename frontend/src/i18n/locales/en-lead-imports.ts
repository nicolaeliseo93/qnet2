/**
 * Lead import history rendered by the generic table (domain `lead-imports`).
 * Kept in a sibling file to hold `en.ts` within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Column labels are resolved by the
 * generic table engine via `t(column.label)`.
 */

export const leadImports = {
  title: 'Import history',
  subtitle: 'Your past lead import runs.',
  forbidden: "You don't have permission to import leads.",
  columns: {
    date: 'Date',
    file: 'File',
    records: 'Records',
    imported: 'Imported',
    errors: 'Errors',
    status: 'Status',
  },
  actions: {
    view: 'View',
    delete: 'Delete',
  },
  deleted: 'Import run deleted successfully.',
  deleteError: 'Unable to delete the import run. Please try again.',
  deleteForbidden: 'You cannot delete this import run.',
}
