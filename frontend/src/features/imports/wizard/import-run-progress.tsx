import { useTranslation } from 'react-i18next'
import { CheckCircle2, Loader2, XCircle } from 'lucide-react'
import { Progress } from '@/components/ui/progress'
import { ImportErrorReportLink } from '@/features/imports/import-error-report-link'
// Side effect: registers this lane's `progress.*` i18n keys (see the module
// doc comment there).
import '@/features/imports/wizard/import-wizard-i18n-summary'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

export interface ImportRunProgressProps {
  domain: string
  run: ImportRunDetail
}

/**
 * Post-confirm progress/outcome view (spec 0033 AC-024): a busy indicator
 * while the server-side `ProcessImportJob` runs (`status === 'processing'`,
 * polled upstream by `useImportWizard`), the final imported/error counts and
 * the CSV error report link (same endpoint as the legacy import flow) once
 * `completed`, or the failure notice for `failed`. The completion
 * notification itself is sent by the backend (`ImportCompletedNotification`)
 * and surfaced in `features/notifications`; this view only notes it was sent.
 */
export function ImportRunProgress({ domain, run }: ImportRunProgressProps) {
  const { t } = useTranslation('importWizard')

  if (run.status === 'processing') {
    return (
      <div className="flex flex-col items-center gap-3 text-center" role="status">
        <Loader2 className="size-6 animate-spin text-primary" aria-hidden="true" />
        <p className="text-sm font-medium">{t('progress.processing')}</p>
        <Progress value={null} className="w-full max-w-sm" aria-label={t('progress.processing')} />
      </div>
    )
  }

  if (run.status === 'failed') {
    return (
      <p className="flex items-center gap-2 text-sm font-medium text-destructive" role="alert">
        <XCircle className="size-5 shrink-0" aria-hidden="true" />
        {t('progress.failed')}
      </p>
    )
  }

  const importedCount = run.imported_rows ?? 0

  return (
    <div className="flex flex-col items-start gap-3">
      <p
        className="flex items-center gap-2 text-sm font-medium text-emerald-600 dark:text-emerald-500"
        role="status"
      >
        <CheckCircle2 className="size-5 shrink-0" aria-hidden="true" />
        {t('progress.completed', { imported: importedCount, errors: run.error_count })}
      </p>
      <p className="text-xs text-muted-foreground">{t('progress.notified')}</p>
      {run.has_error_report ? <ImportErrorReportLink domain={domain} importRunId={run.id} /> : null}
    </div>
  )
}
