import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { OpportunitiesTable } from '@/features/opportunities/opportunities-table'

/**
 * Opportunities page (spec 0040). Light composition only: gates access with
 * `opportunities.viewAny` and mounts the thin Opportunities adapter, which in
 * turn mounts the generic table (`domain="opportunities"`). The generic table
 * owns config loading and loading/error/empty states; no business logic or
 * data fetching lives here.
 */
export default function OpportunitiesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="opportunities.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('opportunities.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <OpportunitiesTable />
      </div>
    </Can>
  )
}
