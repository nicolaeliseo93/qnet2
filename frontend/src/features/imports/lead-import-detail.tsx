import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, FileText, Table2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { StatCard } from '@/components/ui/stat-card'
import { DetailEmpty, DetailField, DetailGrid, DetailMonogram } from '@/components/detail/detail-panel'
import { enumLabelOf } from '@/features/config/enum-label'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ImportErrorReportLink } from '@/features/imports/import-error-report-link'
import { ReviewGrid } from '@/features/imports/wizard/review-grid'
import { resolveFieldLabel, resolveGlobalConfigEntries } from '@/features/imports/wizard/summary-helpers'
import type { ImportRunDetail, ImportRunSummaryReport } from '@/features/imports/wizard/types'

/** This module never leaves the leads domain (spec 0034 scope: OUT). */
const LEADS_DOMAIN = 'leads'

/** Compact tile grid mirroring the stats-panel row (§ui-design). */
const STATS_TILE_GRID_CLASS = 'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6'

const STATUS_BADGE_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  completed: 'secondary',
  failed: 'destructive',
}

/** Small uppercase group label, aligned with the shared detail sections. */
function SectionHeading({ icon, children }: { icon: ReactNode; children: ReactNode }) {
  return (
    <h3 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase [&>svg]:size-3.5">
      {icon}
      {children}
    </h3>
  )
}

interface LeadImportDetailViewProps {
  run: ImportRunDetail
  summary: ImportRunSummaryReport | undefined
  summaryIsLoading: boolean
  summaryIsError: boolean
}

/**
 * Read-only detail of a single lead import run (spec 0034 AC-013): a hero
 * header, the counters as compact tiles, and the run's metadata/errors/records
 * each in its own white card over the gray page background — so every block
 * reads as a distinct surface instead of a single flat panel. `ReviewGrid`
 * (read-only) reuses the wizard grid as a viewer.
 */
export function LeadImportDetailView({
  run,
  summary,
  summaryIsLoading,
  summaryIsError,
}: LeadImportDetailViewProps) {
  const { t } = useTranslation()
  const { t: tWizard } = useTranslation('importWizard')
  const createdAt = formatDateTime(run.created_at)
  const configEntries = summary ? resolveGlobalConfigEntries(run, summary.global_config, t) : []

  return (
    <div className="flex flex-col gap-4 p-4 sm:p-6">
      <header className="flex items-start gap-4">
        <DetailMonogram name={run.original_filename} icon={<FileText />} />
        <div className="min-w-0 flex-1 pt-0.5">
          <h2 className="truncate text-lg leading-tight font-semibold tracking-tight text-foreground">
            {run.original_filename}
          </h2>
          <p className="mt-0.5 truncate text-sm text-muted-foreground">{createdAt}</p>
          <div className="mt-2">
            <Badge variant={STATUS_BADGE_VARIANT[run.status] ?? 'outline'}>
              {enumLabelOf('import_status', run.status)}
            </Badge>
          </div>
        </div>
      </header>

      <div className={STATS_TILE_GRID_CLASS}>
        <StatCard label={t('leadImports.detail.stats.total')} value={run.total_rows} />
        <StatCard label={t('leadImports.detail.stats.imported')} value={run.imported_rows ?? 0} />
        <StatCard label={t('leadImports.detail.stats.modified')} value={run.modified_rows} />
        <StatCard label={t('leadImports.detail.stats.invalid')} value={run.error_rows} />
        <StatCard label={t('leadImports.detail.stats.warning')} value={run.warning_rows} />
        <StatCard label={t('leadImports.detail.stats.duplicate')} value={run.duplicate_rows} />
      </div>

      <Card>
        <CardContent className="flex flex-col gap-4">
          <SectionHeading icon={<FileText />}>{t('leadImports.detail.sections.metadata')}</SectionHeading>
          {summaryIsLoading ? (
            <div className="flex flex-col gap-2">
              <Skeleton className="h-4 w-1/2" />
              <Skeleton className="h-4 w-1/3" />
            </div>
          ) : summary ? (
            <DetailGrid>
              <DetailField label={t('leadImports.detail.metadata.file')}>{run.original_filename}</DetailField>
              <DetailField label={t('leadImports.detail.metadata.dedupStrategy')}>
                {run.dedup_strategy ? (
                  tWizard(`mapping.dedupModes.${run.dedup_strategy}`, { defaultValue: run.dedup_strategy })
                ) : (
                  <DetailEmpty />
                )}
              </DetailField>
              <DetailField label={t('leadImports.detail.metadata.globalConfig')} full>
                {configEntries.length > 0 ? (
                  <ul className="flex flex-col gap-1">
                    {configEntries.map((entry) => (
                      <li key={entry.label}>
                        <span className="text-muted-foreground">{entry.label}:</span> {entry.value}
                      </li>
                    ))}
                  </ul>
                ) : (
                  <DetailEmpty />
                )}
              </DetailField>
              <DetailField label={t('leadImports.detail.metadata.mappedColumns')} full>
                <ul className="flex flex-col gap-1">
                  {summary.mapped_fields.map((entry) => (
                    <li key={entry.column}>
                      <span className="text-muted-foreground">{entry.column}</span>{' '}
                      <span aria-hidden="true">→</span> {resolveFieldLabel(run, entry.field, t)}
                    </li>
                  ))}
                </ul>
              </DetailField>
            </DetailGrid>
          ) : (
            <p className="text-sm text-muted-foreground">
              {summaryIsError ? t('leadImports.detail.loadError') : t('leadImports.detail.metadata.noMetadata')}
            </p>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="flex flex-col gap-4">
          <SectionHeading icon={<AlertTriangle />}>{t('leadImports.detail.sections.errors')}</SectionHeading>
          {run.has_error_report ? (
            <ImportErrorReportLink domain={LEADS_DOMAIN} importRunId={run.id} />
          ) : (
            <DetailEmpty />
          )}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="flex flex-col gap-4">
          <SectionHeading icon={<Table2 />}>{t('leadImports.detail.sections.records')}</SectionHeading>
          <ReviewGrid domain={LEADS_DOMAIN} run={run} readOnly />
        </CardContent>
      </Card>
    </div>
  )
}
