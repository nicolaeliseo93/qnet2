import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { SourcesTable } from '@/features/sources/sources-table'

/**
 * Sources page. Light composition only: gates access with `sources.viewAny`
 * and mounts the thin Sources adapter, which in turn mounts the generic
 * table (`domain="sources"`). The generic table owns config loading and
 * loading/error/empty states; no business logic or data fetching lives here.
 */
export default function SourcesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="sources.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('sources.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <SourcesTable />
      </div>
    </Can>
  )
}
