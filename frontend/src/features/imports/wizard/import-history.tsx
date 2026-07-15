import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Badge, badgeVariants } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
// Side effects: registers the shared `importWizard` namespace (status.*,
// config.select.retry, reused here) and this lane's own `menu.*`/`history.*`
// keys (see the module doc comments there).
import '@/features/imports/wizard/i18n'
import '@/features/imports/wizard/import-history-i18n'
import { useImportHistory } from '@/features/imports/wizard/use-import-history'
import type { ImportRunStatus, ImportRunSummary } from '@/features/imports/wizard/types'
import type { VariantProps } from 'class-variance-authority'

export interface ImportHistoryProps {
  /** Resource key selecting the backend `ImportDefinition`, e.g. `leads`. */
  domain: string
}

type BadgeVariant = VariantProps<typeof badgeVariants>['variant']

/** Badge tone per run status, mirroring the wizard's own status semantics. */
const STATUS_BADGE_VARIANT: Record<ImportRunStatus, BadgeVariant> = {
  analyzing: 'secondary',
  configuring: 'secondary',
  staging: 'secondary',
  reviewing: 'outline',
  processing: 'outline',
  completed: 'default',
  failed: 'destructive',
}

/** Formats an ISO timestamp using the active locale, or '' when invalid. */
function formatRunDate(value: string, language: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat(language, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

/**
 * Paginated history of the actor's own import runs for a domain (spec 0033
 * AC-018): date, original file, record/imported/error counts and status,
 * each row linking back to the wizard to resume/inspect the run. Server
 * state via `useImportHistory` (TanStack Query); this component only renders.
 */
export function ImportHistory({ domain }: ImportHistoryProps) {
  const { t, i18n } = useTranslation('importWizard')
  const { items, pagination, isLoading, isError, refetch, page, setPage } = useImportHistory(domain)

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3">
        <p className="text-sm text-destructive" role="alert">
          {t('history.loadError')}
        </p>
        <Button type="button" variant="outline" size="sm" onClick={() => void refetch()}>
          {t('config.select.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading) {
    return (
      <div className="flex flex-col gap-2" aria-hidden="true">
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-8 w-full" />
      </div>
    )
  }

  if (items.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('history.empty')}</p>
  }

  const totalPages = pagination?.total_pages ?? 1

  return (
    <div className="flex flex-col gap-3">
      <div className="overflow-auto rounded-md border">
        <table className="w-full text-left text-sm">
          <thead className="bg-muted/60">
            <tr>
              <th scope="col" className="whitespace-nowrap px-3 py-2 font-medium">
                {t('history.columns.date')}
              </th>
              <th scope="col" className="whitespace-nowrap px-3 py-2 font-medium">
                {t('history.columns.file')}
              </th>
              <th scope="col" className="whitespace-nowrap px-3 py-2 font-medium">
                {t('history.columns.records')}
              </th>
              <th scope="col" className="whitespace-nowrap px-3 py-2 font-medium">
                {t('history.columns.imported')}
              </th>
              <th scope="col" className="whitespace-nowrap px-3 py-2 font-medium">
                {t('history.columns.errors')}
              </th>
              <th scope="col" className="whitespace-nowrap px-3 py-2 font-medium">
                {t('history.columns.status')}
              </th>
              <th scope="col" className="whitespace-nowrap px-3 py-2 font-medium">
                <span className="sr-only">{t('history.viewRun')}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {items.map((run: ImportRunSummary) => (
              <tr key={run.id} className="border-t">
                <td className="whitespace-nowrap px-3 py-2">{formatRunDate(run.created_at, i18n.language)}</td>
                <td className="max-w-48 truncate px-3 py-2" title={run.original_filename}>
                  {run.original_filename}
                </td>
                <td className="px-3 py-2">{run.total_rows}</td>
                <td className="px-3 py-2">{run.imported_rows ?? '—'}</td>
                <td className="px-3 py-2">{run.error_rows}</td>
                <td className="px-3 py-2">
                  <Badge variant={STATUS_BADGE_VARIANT[run.status]}>{t(`status.${run.status}`)}</Badge>
                </td>
                <td className="whitespace-nowrap px-3 py-2 text-right">
                  <Button asChild variant="ghost" size="sm">
                    <Link to={`/${domain}/import?runId=${run.id}`}>{t('history.viewRun')}</Link>
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {totalPages > 1 ? (
        <div className="flex items-center justify-between gap-2">
          <Button type="button" variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>
            {t('history.previous')}
          </Button>
          <span className="text-xs text-muted-foreground">{t('history.pagination', { page, totalPages })}</span>
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={page >= totalPages}
            onClick={() => setPage(page + 1)}
          >
            {t('history.next')}
          </Button>
        </div>
      ) : null}
    </div>
  )
}
