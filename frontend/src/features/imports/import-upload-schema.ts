import { z } from 'zod'
import type { TFunction } from 'i18next'

/** The single CSV file field of the import upload step. */
export function buildImportUploadSchema(t: TFunction) {
  return z.object({
    file: z.instanceof(File, { error: t('imports.errors.fileRequired') }),
  })
}

export type ImportUploadFormValues = z.infer<ReturnType<typeof buildImportUploadSchema>>
