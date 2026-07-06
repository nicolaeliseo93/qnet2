import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ReferentTypesTable } from '@/features/referent-types/referent-types-table'

/**
 * Referent Types page. Light composition only: gates access with
 * `referent-types.viewAny` and mounts the thin Referent Types adapter, which
 * in turn mounts the generic table (`domain="referent-types"`). The generic
 * table owns config loading and loading/error/empty states; no business logic
 * or data fetching lives here.
 */
export default function ReferentTypesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="referent-types.viewAny"
      fallback={
        <p className="text-sm text-muted-foreground">{t('referentTypes.forbidden')}</p>
      }
    >
      <div className="flex flex-1 flex-col gap-6">
        <ReferentTypesTable />
      </div>
    </Can>
  )
}
