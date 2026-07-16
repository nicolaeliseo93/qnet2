import { useTranslation } from 'react-i18next'
import { CheckCircle2, Loader2 } from 'lucide-react'
import { Progress } from '@/components/ui/progress'
import { ImportErrorReportLink } from '@/features/imports/import-error-report-link'
import { StepAlert } from '@/features/imports/wizard/wizard-ui'
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
      <div className="flex flex-col items-center gap-3 py-10 text-center" role="status">
        <span className="flex size-12 items-center justify-center rounded-full bg-primary/10">
          <Loader2 className="size-6 animate-spin text-primary" aria-hidden="true" />
        </span>
        <p className="text-sm font-medium">{t('progress.processing')}</p>
        <Progress value={null} className="w-full max-w-sm" aria-label={t('progress.processing')} />
      </div>
    )
  }

  if (run.status === 'failed') {
    return <StepAlert>{t('progress.failed')}</StepAlert>
  }

  const importedCount = run.imported_rows ?? 0

  return (
    <div className="flex items-start gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 motion-safe:animate-in motion-safe:fade-in-0 motion-safe:zoom-in-95 motion-safe:duration-300">
      <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
        <CheckCircle2 className="size-5 text-emerald-600 dark:text-emerald-500" aria-hidden="true" />
      </span>
      <div className="flex min-w-0 flex-col items-start gap-1.5">
        <p className="text-sm font-medium text-emerald-700 dark:text-emerald-400" role="status">
          {t('progress.completed', { imported: importedCount, errors: run.error_count })}
        </p>
        <p className="text-xs text-muted-foreground">{t('progress.notified')}</p>
        {run.has_error_report ? <ImportErrorReportLink domain={domain} importRunId={run.id} /> : null}
      </div>
    </div>
  )
}
