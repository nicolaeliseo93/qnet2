import type { TFunction } from 'i18next'
import { ArrowRightLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { StepAlert } from '@/features/imports/wizard/wizard-ui'
import type { ConversionReadiness } from '@/features/imports/wizard/types'

/**
 * Resolves the readable blockers for a not-yet-convertible run. `> 0` is a
 * ternary, never `&&`, so a `rowsWithoutOperator` count is never itself
 * rendered as the literal condition (`frontend.md §10`).
 */
function readinessBlockers(readiness: ConversionReadiness, t: TFunction): string[] {
  const reasons: string[] = []
  if (!readiness.operational_site_set) reasons.push(t('summary.autoConvert.blockers.operationalSite'))
  if (!readiness.campaign_derives_product_line) reasons.push(t('summary.autoConvert.blockers.productLine'))
  if (readiness.rows_without_operator > 0) {
    reasons.push(
      t('summary.autoConvert.blockers.rowsWithoutOperator', { count: readiness.rows_without_operator }),
    )
  }
  return reasons
}

export interface ConversionReadinessSectionProps {
  checked: boolean
  onCheckedChange: (checked: boolean) => void
  readiness: ConversionReadiness
  onBackToReview: () => void
  t: TFunction
}

/**
 * Summary step's auto-convert-to-Opportunity toggle: switching it on reveals
 * the run's `conversion_readiness` — a creatable-rows note when ready, or the
 * specific blockers plus a "back to review" shortcut when not. The caller
 * (`ImportStepSummary`) owns the toggle's boolean state so it can fold it
 * into the confirm payload.
 */
export function ConversionReadinessSection({
  checked,
  onCheckedChange,
  readiness,
  onBackToReview,
  t,
}: ConversionReadinessSectionProps) {
  const blockers = readinessBlockers(readiness, t)
  const ready = blockers.length === 0

  return (
    <div className="flex flex-col gap-3 rounded-lg border bg-muted/30 px-3 py-2.5">
      <div className="flex items-center justify-between gap-3">
        <div className="min-w-0">
          <h4 className="flex items-center gap-2 text-sm font-medium">
            <ArrowRightLeft className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
            {t('summary.autoConvert.title')}
          </h4>
          <p className="mt-0.5 text-xs text-muted-foreground">{t('summary.autoConvert.description')}</p>
        </div>
        <Switch
          checked={checked}
          onCheckedChange={onCheckedChange}
          aria-label={t('summary.autoConvert.title')}
        />
      </div>

      {checked ? (
        <div className="flex flex-col gap-2">
          {ready ? (
            <p className="text-xs text-muted-foreground">
              {t('summary.autoConvert.creatableRows', { count: readiness.creatable_rows })}
            </p>
          ) : (
            <StepAlert tone="warning" role="status">
              <div className="flex flex-col gap-1.5">
                <p className="font-medium">{t('summary.autoConvert.notReady')}</p>
                <ul className="list-disc pl-5">
                  {blockers.map((reason) => (
                    <li key={reason}>{reason}</li>
                  ))}
                </ul>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="mt-1 w-fit"
                  onClick={onBackToReview}
                >
                  {t('summary.autoConvert.backToReview')}
                </Button>
              </div>
            </StepAlert>
          )}
        </div>
      ) : null}
    </div>
  )
}
