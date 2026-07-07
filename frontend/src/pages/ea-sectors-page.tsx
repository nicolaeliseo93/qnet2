import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { EaSectorsTable } from '@/features/ea-sectors/ea-sectors-table'

/**
 * EA Sectors page. Light composition only: gates access with
 * `ea-sectors.viewAny` and mounts the thin EA Sectors adapter, which in turn
 * mounts the generic table (`domain="ea-sectors"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here (mirrors `ReferentTypesPage`).
 */
export default function EaSectorsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="ea-sectors.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('eaSectors.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <EaSectorsTable />
      </div>
    </Can>
  )
}
