import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, Columns3, FileCheck2, Loader2, MoveRight, PackagePlus, Settings2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { BusyState, StatTile, StepAlert, StepSectionHeader } from '@/features/imports/wizard/wizard-ui'
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

/** Small icon+title heading for the summary's sub-sections. */
function SummarySectionTitle({ icon: Icon, children }: { icon: typeof Settings2; children: string }) {
  return (
    <h4 className="flex items-center gap-2 text-sm font-medium">
      <Icon className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
      {children}
    </h4>
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
    return <BusyState label={t('summary.loading')} />
  }

  if (summaryQuery.isError || !summaryQuery.data) {
    return (
      <div className="flex flex-col items-start gap-3">
        <StepAlert>{t('summary.loadError')}</StepAlert>
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
      <StepSectionHeader icon={FileCheck2} title={t('summary.title')} description={run.original_filename} />

        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <StatTile label={t('summary.totals.total')} value={summary.total_rows} />
          <StatTile
            label={t('summary.totals.valid')}
            value={summary.valid_rows}
            tone={summary.valid_rows > 0 ? 'success' : 'default'}
          />
          <StatTile
            label={t('summary.totals.warning')}
            value={summary.warning_rows}
            tone={summary.warning_rows > 0 ? 'warning' : 'default'}
          />
          <StatTile
            label={t('summary.totals.error')}
            value={summary.error_rows}
            tone={summary.error_rows > 0 ? 'destructive' : 'default'}
          />
          <StatTile
            label={t('summary.totals.duplicate')}
            value={summary.duplicate_rows}
            tone={summary.duplicate_rows > 0 ? 'info' : 'default'}
          />
          <StatTile label={t('summary.totals.modified')} value={summary.modified_rows} />
        </div>

        <Separator />

        <div>
          <SummarySectionTitle icon={Settings2}>{t('summary.configTitle')}</SummarySectionTitle>
          {configEntries.length > 0 ? (
            <dl className="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
              {configEntries.map((entry) => (
                <div key={entry.label} className="rounded-lg border bg-muted/30 px-3 py-2">
                  <dt className="text-xs font-medium text-muted-foreground">{entry.label}</dt>
                  <dd className="mt-0.5 font-medium">{entry.value}</dd>
                </div>
              ))}
            </dl>
          ) : (
            <p className="mt-2 text-sm text-muted-foreground">{t('summary.configEmpty')}</p>
          )}
        </div>

        <Separator />

        <div>
          <SummarySectionTitle icon={Columns3}>{t('summary.mappedFieldsTitle')}</SummarySectionTitle>
          <ul className="mt-2 divide-y overflow-hidden rounded-lg border text-sm">
            {summary.mapped_fields.map((entry) => (
              <li key={entry.column} className="flex items-center gap-2 px-3 py-2">
                <span className="min-w-0 flex-1 truncate text-muted-foreground">{entry.column}</span>
                <MoveRight className="size-4 shrink-0 text-primary" aria-hidden="true" />
                <span className="min-w-0 flex-1 truncate font-medium">
                  {resolveFieldLabel(run, entry.field, tLabel)}
                </span>
              </li>
            ))}
          </ul>
        </div>

        {summary.extra_fields.length > 0 ? (
          <div>
            <SummarySectionTitle icon={PackagePlus}>{t('summary.extraFieldsTitle')}</SummarySectionTitle>
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
          <div
            className="flex flex-col gap-1.5 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2.5"
            role="status"
          >
            <h4 className="flex items-center gap-2 text-sm font-medium text-amber-700 dark:text-amber-400">
              <AlertTriangle className="size-4 shrink-0" aria-hidden="true" />
              {t('summary.warningsTitle')}
            </h4>
            <ul className="list-disc pl-6 text-sm text-muted-foreground">
              {summary.warnings.map((warning, index) => (
                <li key={`${index}-${warning}`}>{warning}</li>
              ))}
            </ul>
          </div>
        ) : null}

        {confirmError ? <StepAlert>{confirmError}</StepAlert> : null}

        <Separator />

        <div className="flex justify-end">
          <Button type="button" onClick={onConfirm} disabled={isConfirming}>
            {isConfirming ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
            {isConfirming ? t('summary.confirming') : t('summary.confirm')}
          </Button>
        </div>
    </div>
  )
}
