import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { LeadStatusesTable } from '@/features/lead-statuses/lead-statuses-table'

/**
 * Lead statuses page. Light composition only: gates access with
 * `lead-statuses.viewAny` and mounts the thin Lead Statuses adapter, which
 * in turn mounts the generic table (`domain="lead-statuses"`). The generic
 * table owns config loading and loading/error/empty states; no business
 * logic or data fetching lives here.
 */
export default function LeadStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="lead-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('leadStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <LeadStatusesTable />
      </div>
    </Can>
  )
}
