import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useStatusGroupFormMeta } from '@/features/status-groups/use-status-group-form-meta'
import { StatusGroupFormBody } from '@/features/status-groups/status-group-form-body'
import type { StatusGroupDetail, StatusGroupFormMode } from '@/features/status-groups/types'

interface StatusGroupFormProps {
  mode: StatusGroupFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (statusGroup: StatusGroupDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a status group.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/status-groups` — then hands off to `StatusGroupFormBody`,
 * which reads every field from that context via
 * `MetaField`/`useResourcePermissions()`.
 */
export function StatusGroupForm(props: StatusGroupFormProps) {
  const { t } = useTranslation()
  const meta = useStatusGroupFormMeta(props.mode)

  if (meta.status === 'loading') {
    return (
      <div className="flex flex-col gap-4 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <StatusGroupFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
