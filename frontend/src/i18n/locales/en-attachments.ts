/**
 * Generic polymorphic file-attachment feature (`<DocumentsSection>`): shared
 * across every module that mounts it, so this file carries no per-module
 * knowledge. Sibling file so `en.ts` stays within the engineering size
 * limits (see `.claude/rules/engineering.md` §6).
 */
export const attachments = {
  title: 'Documents',
  dialogSubtitle: 'Files linked to this opportunity',
  dropzoneHint: 'Drop a file here or click to browse',
  dropzoneSubhint: 'PDF, images, spreadsheets and documents',
  uploading: 'Uploading…',
  empty: 'No documents yet.',
  emptyHint: 'Drop a file into the area above to add the first one.',
  preview: 'Preview',
  download: 'Download',
  deleteAction: 'Delete document',
  deleteConfirm: 'Delete "{{name}}"? This cannot be undone.',
  errors: {
    load: 'Unable to load the documents. Please try again.',
    upload: 'Unable to upload the file. Please try again.',
    delete: 'Unable to delete the document. Please try again.',
  },
}
