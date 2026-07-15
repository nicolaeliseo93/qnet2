import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, Loader2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { ReviewGrid } from '@/features/imports/wizard/review-grid'
import type { ImportRunDetail, ImportRunRowCounts } from '@/features/imports/wizard/types'

export interface ImportStepReviewProps {
  domain: string
  run: ImportRunDetail | null
  onContinue: () => void
}

function countsFromRun(run: ImportRunDetail): ImportRunRowCounts {
  return {
    total: run.total_rows,
    valid_rows: run.valid_rows,
    warning_rows: run.warning_rows,
    error_rows: run.error_rows,
    duplicate_rows: run.duplicate_rows,
    modified_rows: run.modified_rows,
  }
}

/** A single `dt`/`dd` counter, badged once `value > 0` for the tones that flag attention. */
function ReviewCountStat({
  label,
  value,
  badgeVariant,
  badgeClassName,
}: {
  label: string
  value: number
  badgeVariant?: 'destructive' | 'outline'
  badgeClassName?: string
}) {
  return (
    <div>
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium">
        {badgeVariant && value > 0 ? (
          <Badge variant={badgeVariant} className={badgeClassName}>
            {value}
          </Badge>
        ) : (
          value
        )}
      </dd>
    </div>
  )
}

/**
 * Review step (AC-023): an AG Grid SSRM grid (`ReviewGrid`) over the staged
 * rows of a `reviewing` run, with inline editing PATCHing each field back to
 * the server. The run stays static while this step is mounted (the wizard
 * only polls during `analyzing`/`staging`/`processing`, see
 * `use-import-wizard.ts`), so the header counters start from the run and are
 * then kept in sync purely from each edit's server-returned counts — no
 * effect needed to reconcile the two.
 */
export function ImportStepReview({ domain, run, onContinue }: ImportStepReviewProps) {
  const { t } = useTranslation('importWizard')
  const [counts, setCounts] = useState<ImportRunRowCounts | null>(() => (run ? countsFromRun(run) : null))

  if (run === null) {
    return (
      <Card>
        <CardContent className="flex items-center gap-2 pt-4 text-sm text-muted-foreground" role="status">
          <Loader2 className="size-4 animate-spin" aria-hidden="true" />
          {t('review.loading')}
        </CardContent>
      </Card>
    )
  }

  if (run.status === 'staging') {
    return (
      <Card>
        <CardContent className="flex items-center gap-2 pt-4 text-sm text-muted-foreground" role="status">
          <Loader2 className="size-4 animate-spin" aria-hidden="true" />
          {t('status.staging')}
        </CardContent>
      </Card>
    )
  }

  const activeCounts = counts ?? countsFromRun(run)
  const needsAttention = activeCounts.error_rows + activeCounts.warning_rows + activeCounts.duplicate_rows > 0

  return (
    <Card>
      <CardContent className="flex flex-col gap-4 pt-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h3 className="text-base font-semibold">{t('review.title')}</h3>
          {needsAttention ? (
            <span className="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400" role="alert">
              <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
              {t('review.needsAttention')}
            </span>
          ) : null}
        </div>

        <dl className="grid grid-cols-3 gap-3 text-xs sm:grid-cols-6">
          <ReviewCountStat label={t('review.counts.total')} value={activeCounts.total} />
          <ReviewCountStat label={t('review.counts.valid')} value={activeCounts.valid_rows} />
          <ReviewCountStat
            label={t('review.counts.warning')}
            value={activeCounts.warning_rows}
            badgeVariant="outline"
            badgeClassName="border-amber-500 text-amber-700 dark:text-amber-400"
          />
          <ReviewCountStat label={t('review.counts.error')} value={activeCounts.error_rows} badgeVariant="destructive" />
          <ReviewCountStat
            label={t('review.counts.duplicate')}
            value={activeCounts.duplicate_rows}
            badgeVariant="outline"
            badgeClassName="border-sky-500 text-sky-700 dark:text-sky-400"
          />
          <ReviewCountStat label={t('review.counts.modified')} value={activeCounts.modified_rows} />
        </dl>

        <ReviewGrid domain={domain} run={run} onRowUpdated={(_row, nextCounts) => setCounts(nextCounts)} />

        <div className="flex justify-end">
          <Button type="button" onClick={onContinue}>
            {t('review.continue')}
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
