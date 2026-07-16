import { useTranslation } from 'react-i18next'
import { History, KeyRound, ShieldCheck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import {
  DetailEmpty,
  DetailError,
  DetailHero,
  DetailLoading,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRole } from '@/features/roles/api'
import { groupPermissions, permissionAbility } from '@/features/roles/permission-groups'

interface RoleDetailProps {
  roleId: number
}

/**
 * Read-only detail of a single role, fetched fresh from the (re-authorized)
 * detail endpoint. Permissions are grouped by resource for readability, laid
 * out with the shared detail kit; rendered inside a Sheet.
 */
export function RoleDetailView({ roleId }: RoleDetailProps) {
  const { t } = useTranslation()
  const {
    data: role,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['roles', 'detail', roleId], () => fetchRole(roleId))

  if (isError) {
    return (
      <DetailError
        message={t('roles.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !role) {
    return <DetailLoading />
  }

  const createdAt = formatDateTime(role.created_at)
  const groups = groupPermissions(role.permissions)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={role.name} icon={<ShieldCheck />} />}
        title={role.name}
      />

      <DetailSection
        title={t('roles.form.permissions')}
        icon={<KeyRound />}
        action={
          groups.length > 0 ? (
            <Badge variant="secondary">{role.permissions.length}</Badge>
          ) : null
        }
      >
        {groups.length > 0 ? (
          <div className="flex flex-col gap-4">
            {groups.map((group) => (
              <div key={group.resource} className="flex flex-col gap-1.5">
                <span className="text-xs font-medium text-muted-foreground">{group.resource}</span>
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
          <DetailEmpty />
        )}
      </DetailSection>

      {role.authorization.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="roles" id={role.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('roles.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
