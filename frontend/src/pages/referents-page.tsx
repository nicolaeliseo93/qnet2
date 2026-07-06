import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ReferentsTable } from '@/features/referents/referents-table'

/**
 * Referents page. Light composition only: gates access with
 * `referents.viewAny` and mounts the thin Referents adapter, which in turn
 * mounts the generic table (`domain="referents"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here.
 */
export default function ReferentsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="referents.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('referents.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ReferentsTable />
      </div>
    </Can>
  )
}
