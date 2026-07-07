import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { AttributesTable } from '@/features/attributes/attributes-table'

/**
 * Attributes page. Light composition only: gates access with
 * `attributes.viewAny` and mounts the thin Attributes adapter, which in turn
 * mounts the generic table (`domain="attributes"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here (mirrors `ReferentTypesPage`).
 */
export default function AttributesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="attributes.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('attributes.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <AttributesTable />
      </div>
    </Can>
  )
}
