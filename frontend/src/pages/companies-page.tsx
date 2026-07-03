import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { CompaniesTable } from '@/features/companies/companies-table'

/**
 * Companies page. Light composition only: gates access with
 * `companies.viewAny` and mounts the thin Companies adapter, which in turn
 * mounts the generic table (`domain="companies"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here.
 */
export default function CompaniesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="companies.viewAny"
      fallback={
        <p className="text-sm text-muted-foreground">{t('companies.forbidden')}</p>
      }
    >
      <div className="flex flex-1 flex-col gap-6">
        <CompaniesTable />
      </div>
    </Can>
  )
}
