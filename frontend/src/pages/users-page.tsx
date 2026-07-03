import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { UsersTable } from '@/features/users/users-table'

/**
 * Users page. Light composition only: gates access with `users.viewAny` and
 * mounts the thin Users adapter, which in turn mounts the generic table
 * (`domain="users"`). The generic table owns config loading and
 * loading/error/empty states; no business logic or data fetching lives here.
 */
export default function UsersPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="users.viewAny"
      fallback={
        <p className="text-sm text-muted-foreground">{t('users.forbidden')}</p>
      }
    >
      <div className="flex flex-1 flex-col gap-6">
        <UsersTable />
      </div>
    </Can>
  )
}
