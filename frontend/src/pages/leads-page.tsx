import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { LeadsTable } from '@/features/leads/leads-table'

/**
 * Leads page. Light composition only: gates access with `leads.viewAny` and
 * mounts the thin Leads adapter, which in turn mounts the generic table
 * (`domain="leads"`). The generic table owns config loading and
 * loading/error/empty states; no business logic or data fetching lives here.
 */
export default function LeadsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="leads.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('leads.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <LeadsTable />
      </div>
    </Can>
  )
}
