import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRole } from '@/features/roles/api'
import {
  groupPermissions,
  permissionAbility,
} from '@/features/roles/permission-groups'

interface RoleDetailProps {
  roleId: number
}

/**
 * Read-only detail of a single role, fetched fresh from the (re-authorized)
 * detail endpoint. Handles loading and error states; rendered inside a Sheet.
 * Permissions are grouped by resource for readability.
 */
export function RoleDetailView({ roleId }: RoleDetailProps) {
  const { t, i18n } = useTranslation()
  const {
    data: role,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['roles', 'detail', roleId], () => fetchRole(roleId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('roles.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !role) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-6 w-1/2" />
        <Skeleton className="h-6 w-2/3" />
        <Skeleton className="h-6 w-1/3" />
      </div>
    )
  }

  const createdAt = formatDateTime(role.created_at, i18n.language)
  const groups = groupPermissions(role.permissions)

  return (
    <dl className="flex flex-col gap-4 overflow-y-auto p-4 text-sm">
      <Field label={t('roles.form.name')}>{role.name}</Field>
      <Field label={t('roles.form.permissions')}>
        {groups.length > 0 ? (
          <div className="flex flex-col gap-3">
            {groups.map((group) => (
              <div key={group.resource} className="flex flex-col gap-1">
                <span className="text-xs font-medium uppercase text-muted-foreground">
                  {group.resource}
                </span>
                <div className="flex flex-wrap gap-1">
                  {group.permissions.map((permission) => (
                    <Badge key={permission} variant="secondary">
                      {permissionAbility(permission)}
                    </Badge>
                  ))}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <span className="text-muted-foreground">—</span>
        )}
      </Field>
      <Field label={t('roles.columns.created_at')}>
        {createdAt || <span className="text-muted-foreground">—</span>}
      </Field>
    </dl>
  )
}

function Field({
  label,
  children,
}: {
  label: string
  children: React.ReactNode
}) {
  return (
    <div className="flex flex-col gap-1">
      <dt className="font-medium text-muted-foreground">{label}</dt>
      <dd>{children}</dd>
    </div>
  )
}

function formatDateTime(value: string | null, language: string): string {
  if (!value) {
    return ''
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  return new Intl.DateTimeFormat(language, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}
