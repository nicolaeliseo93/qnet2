import { z } from 'zod'
import type { TFunction } from 'i18next'

/** File extensions accepted client-side (UX only — the server re-validates via `mimes:`/`extensions:`). */
const ACCEPTED_EXTENSIONS = ['.csv', '.xlsx', '.xls']

function hasAcceptedExtension(file: File): boolean {
  const lower = file.name.toLowerCase()
  return ACCEPTED_EXTENSIONS.some((extension) => lower.endsWith(extension))
}

/** The single file field of the wizard's upload step. */
export function buildImportWizardUploadSchema(t: TFunction) {
  return z.object({
    file: z
      .instanceof(File, { error: t('upload.errors.fileRequired') })
      .refine(hasAcceptedExtension, { error: t('upload.errors.fileType') }),
  })
}

export type ImportWizardUploadFormValues = z.infer<ReturnType<typeof buildImportWizardUploadSchema>>
