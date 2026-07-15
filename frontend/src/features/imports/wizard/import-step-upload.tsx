import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Loader2, Upload } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import {
  buildImportWizardUploadSchema,
  type ImportWizardUploadFormValues,
} from '@/features/imports/wizard/import-upload-schema'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

export interface ImportStepUploadProps {
  /** `null` before any file has been uploaded in this session. */
  run: ImportRunDetail | null
  isUploading: boolean
  uploadError: string | null
  onUpload: (file: File) => void
  onContinue: () => void
}

/**
 * Upload step (AC-020): a plain file form while no run exists yet; once the
 * server-side `AnalyzeImportJob` finishes (`status` moves past `analyzing`),
 * shows the detected columns/rows/duplicate-columns summary and an explicit
 * "continue" action — the user reviews the analysis before proceeding.
 */
export function ImportStepUpload({ run, isUploading, uploadError, onUpload, onContinue }: ImportStepUploadProps) {
  const { t } = useTranslation('importWizard')
  const form = useForm<ImportWizardUploadFormValues>({
    resolver: zodResolver(buildImportWizardUploadSchema(t)),
  })

  const onSubmit = form.handleSubmit((values) => onUpload(values.file))

  if (run === null) {
    return (
      <Card>
        <CardContent className="pt-4">
          <Form {...form}>
            <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
              <FormField
                control={form.control}
                name="file"
                render={({ field: { onChange, onBlur, name, ref } }) => (
                  <FormItem>
                    <FormLabel required>{t('upload.fileLabel')}</FormLabel>
                    <FormControl>
                      <Input
                        ref={ref}
                        name={name}
                        onBlur={onBlur}
                        type="file"
                        accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                        onChange={(event) => onChange(event.target.files?.[0])}
                      />
                    </FormControl>
                    <FormMessage role="alert" />
                  </FormItem>
                )}
              />

              {uploadError ? (
                <p className="text-sm text-destructive" role="alert">
                  {uploadError}
                </p>
              ) : null}

              <div className="flex justify-end">
                <Button type="submit" disabled={isUploading}>
                  <Upload aria-hidden="true" />
                  {isUploading ? t('upload.uploading') : t('upload.submit')}
                </Button>
              </div>
            </form>
          </Form>
        </CardContent>
      </Card>
    )
  }

  if (run.status === 'analyzing') {
    return (
      <Card>
        <CardContent className="flex items-center gap-2 pt-4 text-sm text-muted-foreground" role="status">
          <Loader2 className="size-4 animate-spin" aria-hidden="true" />
          {t('upload.analyzing')}
        </CardContent>
      </Card>
    )
  }

  const columnsCount = run.detected_columns?.length ?? 0
  const duplicateCount = run.detected_columns?.filter((column) => column.duplicate).length ?? 0

  return (
    <Card>
      <CardContent className="flex flex-col gap-4 pt-4">
        <h3 className="text-base font-semibold">{t('upload.summaryTitle')}</h3>
        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          <div>
            <dt className="text-muted-foreground">{t('upload.columnsLabel')}</dt>
            <dd className="font-medium">{columnsCount}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">{t('upload.rowsLabel')}</dt>
            <dd className="font-medium">{run.total_rows}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">{t('upload.duplicateColumnsLabel')}</dt>
            <dd className="font-medium">
              {duplicateCount > 0 ? (
                <Badge variant="destructive">{duplicateCount}</Badge>
              ) : (
                t('upload.noDuplicateColumns')
              )}
            </dd>
          </div>
        </dl>

        <div className="flex justify-end">
          <Button type="button" onClick={onContinue}>
            {t('upload.continue')}
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
