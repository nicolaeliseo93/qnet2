import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { CompanySitesTable } from '@/features/company-sites/company-sites-table'

/**
 * Company Sites page. Light composition only: gates access with
 * `company-sites.viewAny` and mounts the thin Company Sites adapter, which in
 * turn mounts the generic table (`domain="company-sites"`). The generic table
 * owns config loading and loading/error/empty states; no business logic or
 * data fetching lives here.
 */
export default function CompanySitesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="company-sites.viewAny"
      fallback={
        <p className="text-sm text-muted-foreground">{t('companySites.forbidden')}</p>
      }
    >
      <div className="flex flex-1 flex-col gap-6">
        <CompanySitesTable />
      </div>
    </Can>
  )
}
