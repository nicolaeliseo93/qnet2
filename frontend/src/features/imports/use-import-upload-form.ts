import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import {
  buildImportUploadSchema,
  type ImportUploadFormValues,
} from '@/features/imports/import-upload-schema'

interface UseImportUploadFormArgs {
  /** Called with the selected file once the (trivial) schema validates. */
  onSubmitFile: (file: File) => void
}

/** RHF + Zod wrapper around the single CSV file field of the upload step. */
export function useImportUploadForm({ onSubmitFile }: UseImportUploadFormArgs) {
  const { t } = useTranslation()

  const form = useForm<ImportUploadFormValues>({
    resolver: zodResolver(buildImportUploadSchema(t)),
  })

  const onSubmit = form.handleSubmit((values) => {
    onSubmitFile(values.file)
  })

  return { form, onSubmit }
}
