import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { OpportunityStatusesTable } from '@/features/opportunity-statuses/opportunity-statuses-table'

/**
 * Opportunity statuses page. Light composition only: gates access with
 * `opportunity-statuses.viewAny` and mounts the thin Opportunity Statuses
 * adapter, which in turn mounts the generic table
 * (`domain="opportunity-statuses"`). The generic table owns config loading
 * and loading/error/empty states; no business logic or data fetching lives
 * here.
 */
export default function OpportunityStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="opportunity-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('opportunityStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <OpportunityStatusesTable />
      </div>
    </Can>
  )
}
