import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { StatusGroupsTable } from '@/features/status-groups/status-groups-table'

/**
 * Status groups page. Light composition only: gates access with
 * `status-groups.viewAny` and mounts the thin Status Groups adapter, which
 * in turn mounts the generic table (`domain="status-groups"`). The generic
 * table owns config loading and loading/error/empty states; no business
 * logic or data fetching lives here.
 */
export default function StatusGroupsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="status-groups.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('statusGroups.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <StatusGroupsTable />
      </div>
    </Can>
  )
}
