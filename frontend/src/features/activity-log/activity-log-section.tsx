import { useTranslation } from 'react-i18next'
import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useActivityLog } from '@/features/activity-log/use-activity-log'
import type { ActivityLogEntry } from '@/features/activity-log/types'

export interface ActivityLogSectionProps {
  /** Registry key of the aggregating resource, e.g. "users". */
  resource: string
  /** Id of the root record whose aggregated activity log is shown. */
  id: number
}

const SKELETON_ROWS = 3

/**
 * Reusable timeline of a resource record's aggregated activity log
 * (spec 0034). Mounted both as a `DetailSection` (user detail) and inside a
 * row-action Dialog (users table) — this component owns no gating, callers
 * decide when it is authorized to mount.
 */
export function ActivityLogSection({ resource, id }: ActivityLogSectionProps) {
  const { t, i18n } = useTranslation()
  const {
    data,
    isLoading,
    isError,
    refetch,
    hasNextPage,
    isFetchingNextPage,
    fetchNextPage,
  } = useActivityLog(resource, id)

  if (isLoading) {
    return (
      <div className="flex flex-col gap-2">
        {Array.from({ length: SKELETON_ROWS }).map((_, index) => (
          <Skeleton key={index} className="h-14 w-full" />
        ))}
      </div>
    )
  }

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-2">
        <p className="text-xs text-destructive">{t('activityLog.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  const entries = data?.pages.flatMap((page) => page.items) ?? []

  if (entries.length === 0) {
    return <p className="text-xs text-muted-foreground">{t('activityLog.empty')}</p>
  }

  return (
    <div className="flex flex-col gap-3">
      <ol className="flex flex-col gap-2">
        {entries.map((entry) => (
          <ActivityLogItem key={entry.id} entry={entry} language={i18n.language} />
        ))}
      </ol>
      {hasNextPage ? (
        <Button
          variant="outline"
          size="sm"
          onClick={() => fetchNextPage()}
          disabled={isFetchingNextPage}
        >
          {isFetchingNextPage ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
          {t('activityLog.loadMore')}
        </Button>
      ) : null}
    </div>
  )
}

interface ActivityLogItemProps {
  entry: ActivityLogEntry
  language: string
}

/** A single timeline row: when/who/what happened, plus its field-level diff. */
function ActivityLogItem({ entry, language }: ActivityLogItemProps) {
  const { t } = useTranslation()
  const causerName = entry.causer.name ?? t('activityLog.systemCauser')

  return (
    <li className="flex flex-col gap-1 rounded-lg border p-2.5 text-xs">
      <div className="flex flex-wrap items-center justify-between gap-x-2 gap-y-0.5">
        <span className="font-medium text-foreground">
          {t(`activityLog.events.${entry.event}`)}
          {' · '}
          {t(`activityLog.modules.${entry.module}`, { defaultValue: entry.module })}
        </span>
        <span className="text-muted-foreground">{formatDateTime(entry.logged_at, language)}</span>
      </div>
      <p className="text-muted-foreground">{causerName}</p>
      {entry.changes.length > 0 ? (
        <ul className="flex flex-col gap-0.5 border-t pt-1.5">
          {entry.changes.map((change) => (
            <li key={change.field} className="text-foreground">
              <span className="font-medium">
                {t(`activityLog.fields.${change.field}`, { defaultValue: change.field })}
              </span>
              {': '}
              {formatChangeValue(change.old_value)}
              {' → '}
              {formatChangeValue(change.new_value)}
            </li>
          ))}
        </ul>
      ) : null}
    </li>
  )
}

function formatDateTime(value: string, language: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }
  return new Intl.DateTimeFormat(language, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

/** Renders a raw before/after value as a compact string for the diff line. */
function formatChangeValue(value: unknown): string {
  if (value === null || value === undefined) {
    return '—'
  }
  if (typeof value === 'object') {
    return JSON.stringify(value)
  }
  return String(value)
}
