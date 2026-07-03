import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ImportErrorReportLink } from '@/features/imports/import-error-report-link'
import type {
  ImportPreview as ImportPreviewData,
  ImportRun,
} from '@/features/imports/types'

/** Renders a row's raw values as a compact `column: value` summary. */
function formatRowValues(values: Record<string, string>): string {
  return Object.entries(values)
    .map(([column, value]) => `${column}: ${value}`)
    .join(', ')
}

interface ImportPreviewProps {
  domain: string
  importRun: ImportRun
  preview: ImportPreviewData
  onConfirm: () => void
  onCancel: () => void
  isConfirming: boolean
  confirmError: string | null
}

/**
 * The `awaiting_confirmation` step: valid-rows sample, invalid-rows sample
 * (with the row's original values and its motivated errors), the error
 * report link and the Confirm/Cancel actions. Confirm triggers `onConfirm`
 * (wired to `POST .../confirm` by the caller); the dialog then switches to
 * `ImportProgress` for the `processing` phase.
 */
export function ImportPreview({
  domain,
  importRun,
  preview,
  onConfirm,
  onCancel,
  isConfirming,
  confirmError,
}: ImportPreviewProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <Badge variant="secondary">{t('imports.status.awaiting_confirmation')}</Badge>
        <span className="truncate text-sm text-muted-foreground">
          {importRun.original_filename}
        </span>
      </div>

      <dl className="grid grid-cols-3 gap-3 text-sm">
        <div>
          <dt className="text-muted-foreground">{t('imports.summary.total')}</dt>
          <dd className="font-medium">{importRun.total_rows}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{t('imports.summary.valid')}</dt>
          <dd className="font-medium">{importRun.valid_rows}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{t('imports.summary.invalid')}</dt>
          <dd className="font-medium">{importRun.invalid_rows}</dd>
        </div>
      </dl>

      {preview.valid_sample.length > 0 ? (
        <section className="flex flex-col gap-2">
          <h3 className="text-sm font-semibold">{t('imports.preview.validSampleTitle')}</h3>
          <div className="max-h-48 overflow-auto rounded-md border">
            <table className="w-full text-left text-xs">
              <thead className="bg-muted/60">
                <tr>
                  {preview.columns.map((column) => (
                    <th key={column} scope="col" className="whitespace-nowrap px-2 py-1 font-medium">
                      {column}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {preview.valid_sample.map((row, index) => (
                  // The sample carries no stable row id; index is safe here (static list, no reordering).
                  <tr key={index} className="border-t">
                    {preview.columns.map((column) => (
                      <td key={column} className="truncate px-2 py-1">
                        {row[column] ?? ''}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : null}

      {preview.invalid_sample.length > 0 ? (
        <section className="flex flex-col gap-2">
          <h3 className="text-sm font-semibold">{t('imports.preview.invalidSampleTitle')}</h3>
          <div className="max-h-48 overflow-auto rounded-md border">
            <table className="w-full text-left text-xs">
              <thead className="bg-muted/60">
                <tr>
                  <th scope="col" className="whitespace-nowrap px-2 py-1 font-medium">
                    {t('imports.preview.rowNumber')}
                  </th>
                  <th scope="col" className="whitespace-nowrap px-2 py-1 font-medium">
                    {t('imports.preview.values')}
                  </th>
                  <th scope="col" className="whitespace-nowrap px-2 py-1 font-medium">
                    {t('imports.preview.reason')}
                  </th>
                </tr>
              </thead>
              <tbody>
                {preview.invalid_sample.map((row) => (
                  <tr key={row.row_number} className="border-t">
                    <td className="px-2 py-1">{row.row_number}</td>
                    <td className="px-2 py-1">{formatRowValues(row.values)}</td>
                    <td className="px-2 py-1">{row.errors.join(', ')}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : null}

      {importRun.has_error_report ? (
        <ImportErrorReportLink domain={domain} importRunId={importRun.id} />
      ) : null}

      {confirmError ? (
        <p className="text-sm text-destructive" role="alert">
          {confirmError}
        </p>
      ) : null}

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel} disabled={isConfirming}>
          {t('common.cancel')}
        </Button>
        <Button type="button" onClick={onConfirm} disabled={isConfirming}>
          {isConfirming ? t('imports.buttons.confirming') : t('imports.buttons.confirm')}
        </Button>
      </div>
    </div>
  )
}
