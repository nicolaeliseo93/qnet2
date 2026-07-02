import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { AppBreadcrumbs } from '@/routes/breadcrumbs'
import { RolesTable } from '@/features/roles/roles-table'

/**
 * Roles page. Light composition only: gates access with `roles.viewAny` and
 * mounts the thin Roles adapter, which in turn mounts the generic table
 * (`domain="roles"`). The generic table owns config loading and
 * loading/error/empty states; no business logic or data fetching lives here.
 */
export default function RolesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="roles.viewAny"
      fallback={
        <p className="text-sm text-muted-foreground">{t('roles.forbidden')}</p>
      }
    >
      <div className="flex flex-1 flex-col gap-6">
        <AppBreadcrumbs />

        <RolesTable />
      </div>
    </Can>
  )
}
