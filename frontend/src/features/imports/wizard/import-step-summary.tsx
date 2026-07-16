import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { getImportRunSummary } from '@/features/imports/wizard/api'
import { importWizardKeys } from '@/features/imports/wizard/query-keys'
import { ImportRunProgress } from '@/features/imports/wizard/import-run-progress'
import { resolveFieldLabel, resolveGlobalConfigEntries } from '@/features/imports/wizard/summary-helpers'
// Side effect: registers this lane's `summary.*`/`progress.*` i18n keys (see
// the module doc comment there).
import '@/features/imports/wizard/import-wizard-i18n-summary'
import type { ImportRunDetail, ImportRunSummaryReport } from '@/features/imports/wizard/types'

export interface ImportStepSummaryProps {
  domain: string
  run: ImportRunDetail | null
  onConfirm: () => void
  isConfirming: boolean
  confirmError: string | null
}

interface SummaryStatProps {
  label: string
  value: number
}

/** A single totals cell (rows in the review are `error_rows`/`invalid_rows`, unchanged). */
function SummaryStat({ label, value }: SummaryStatProps) {
  return (
    <div>
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium">{value}</dd>
    </div>
  )
}

/**
 * Summary step (AC-024): while `reviewing`, fetches and renders the
 * pre-confirm report (totals, selected global values, mapped columns, extra
 * fields, warnings) with an explicit confirm action wired to `onConfirm`
 * (`POST .../confirm`, orchestrated upstream). Once confirmed the run leaves
 * `reviewing`, and this step hands off to `ImportRunProgress` for the
 * processing/completed/failed outcome (polling already covered by
 * `useImportWizard`).
 */
export function ImportStepSummary({ domain, run, onConfirm, isConfirming, confirmError }: ImportStepSummaryProps) {
  const { t } = useTranslation('importWizard')
  // Field/global labels are backend default-namespace i18n keys
  // (`imports.leads.fields.*` / `imports.leads.global.*`).
  const { t: tLabel } = useTranslation()
  const runId = run?.id ?? null
  const isReviewing = run?.status === 'reviewing'

  const summaryQuery = useQuery<ImportRunSummaryReport>({
    queryKey: runId != null ? importWizardKeys.summary(domain, runId) : importWizardKeys.domain(domain),
    queryFn: () => getImportRunSummary(domain, runId as number),
    enabled: runId != null && isReviewing,
  })

  if (!run) return null

  if (!isReviewing) {
    return <ImportRunProgress domain={domain} run={run} />
  }

  if (summaryQuery.isLoading) {
    return (
      <p className="text-sm text-muted-foreground" role="status">
        {t('summary.loading')}
      </p>
    )
  }

  if (summaryQuery.isError || !summaryQuery.data) {
    return (
      <div className="flex flex-col items-start gap-3">
        <p className="text-sm text-destructive" role="alert">
          {t('summary.loadError')}
        </p>
        <Button type="button" variant="outline" size="sm" onClick={() => void summaryQuery.refetch()}>
          {t('config.select.retry')}
        </Button>
      </div>
    )
  }

  const summary = summaryQuery.data
  const configEntries = resolveGlobalConfigEntries(run, summary.global_config, tLabel)

  return (
    <div className="flex flex-col gap-4">
      <h3 className="text-base font-semibold">{t('summary.title')}</h3>

        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          <SummaryStat label={t('summary.totals.total')} value={summary.total_rows} />
          <SummaryStat label={t('summary.totals.valid')} value={summary.valid_rows} />
          <SummaryStat label={t('summary.totals.warning')} value={summary.warning_rows} />
          <SummaryStat label={t('summary.totals.error')} value={summary.error_rows} />
          <SummaryStat label={t('summary.totals.duplicate')} value={summary.duplicate_rows} />
          <SummaryStat label={t('summary.totals.modified')} value={summary.modified_rows} />
        </dl>

        <Separator />

        <div>
          <h4 className="text-sm font-medium">{t('summary.configTitle')}</h4>
          {configEntries.length > 0 ? (
            <dl className="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
              {configEntries.map((entry) => (
                <div key={entry.label}>
                  <dt className="text-muted-foreground">{entry.label}</dt>
                  <dd className="font-medium">{entry.value}</dd>
                </div>
              ))}
            </dl>
          ) : (
            <p className="mt-2 text-sm text-muted-foreground">{t('summary.configEmpty')}</p>
          )}
        </div>

        <Separator />

        <div>
          <h4 className="text-sm font-medium">{t('summary.mappedFieldsTitle')}</h4>
          <ul className="mt-2 flex flex-col gap-1 text-sm">
            {summary.mapped_fields.map((entry) => (
              <li key={entry.column} className="flex items-center gap-2">
                <span className="text-muted-foreground">{entry.column}</span>
                <span aria-hidden="true">→</span>
                <span className="font-medium">{resolveFieldLabel(run, entry.field, tLabel)}</span>
              </li>
            ))}
          </ul>
        </div>

        {summary.extra_fields.length > 0 ? (
          <div>
            <h4 className="text-sm font-medium">{t('summary.extraFieldsTitle')}</h4>
            <div className="mt-2 flex flex-wrap gap-1.5">
              {summary.extra_fields.map((field) => (
                <Badge key={field} variant="secondary">
                  {field}
                </Badge>
              ))}
            </div>
          </div>
        ) : null}

        {summary.warnings.length > 0 ? (
          <div className="flex flex-col gap-1.5" role="status">
            <h4 className="flex items-center gap-2 text-sm font-medium text-amber-600 dark:text-amber-500">
              <AlertTriangle className="size-4 shrink-0" aria-hidden="true" />
              {t('summary.warningsTitle')}
            </h4>
            <ul className="list-disc pl-5 text-sm text-muted-foreground">
              {summary.warnings.map((warning, index) => (
                <li key={`${index}-${warning}`}>{warning}</li>
              ))}
            </ul>
          </div>
        ) : null}

        {confirmError ? (
          <p className="text-sm text-destructive" role="alert">
            {confirmError}
          </p>
        ) : null}

        <div className="flex justify-end">
          <Button type="button" onClick={onConfirm} disabled={isConfirming}>
            {isConfirming ? t('summary.confirming') : t('summary.confirm')}
          </Button>
        </div>
    </div>
  )
}
