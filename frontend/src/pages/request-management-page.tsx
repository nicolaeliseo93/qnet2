import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { RequestManagementTable } from '@/features/request-management/request-management-table'

/**
 * Request Management page (spec 0049). Light composition only: gates access
 * with `request-management.viewAny` (the module's OWN permission set, D-2 —
 * never `opportunities.viewAny`) and mounts the thin adapter, which in turn
 * mounts the generic table (`domain="request-management"`). The generic
 * table owns config loading and loading/error/empty states; no business
 * logic or data fetching lives here.
 */
export default function RequestManagementPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="request-management.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('requestManagement.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <RequestManagementTable />
      </div>
    </Can>
  )
}
