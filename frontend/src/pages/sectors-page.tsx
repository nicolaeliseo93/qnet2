import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { SectorsTable } from '@/features/sectors/sectors-table'

/**
 * Sectors page. Light composition only: gates access with
 * `sectors.viewAny` and mounts the thin Sectors adapter, which in turn
 * mounts the generic table (`domain="sectors"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here (mirrors `ReferentTypesPage`).
 */
export default function SectorsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="sectors.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('sectors.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <SectorsTable />
      </div>
    </Can>
  )
}
