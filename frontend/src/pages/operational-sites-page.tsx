import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { OperationalSitesTable } from '@/features/operational-sites/operational-sites-table'

/**
 * Operational Sites page. Light composition only: gates access with
 * `operational-sites.viewAny` and mounts the thin Operational Sites adapter,
 * which in turn mounts the generic table (`domain="operational-sites"`). The
 * generic table owns config loading and loading/error/empty states; no
 * business logic or data fetching lives here.
 */
export default function OperationalSitesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="operational-sites.viewAny"
      fallback={
        <p className="text-sm text-muted-foreground">{t('operationalSites.forbidden')}</p>
      }
    >
      <div className="flex flex-1 flex-col gap-6">
        <OperationalSitesTable />
      </div>
    </Can>
  )
}
