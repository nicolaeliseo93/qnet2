import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useProjectStatusFormMeta } from '@/features/project-statuses/use-project-status-form-meta'
import { ProjectStatusFormBody } from '@/features/project-statuses/project-status-form-body'
import type { ProjectStatusDetail, ProjectStatusFormMode } from '@/features/project-statuses/types'

interface ProjectStatusFormProps {
  mode: ProjectStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (projectStatus: ProjectStatusDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a project
 * status. Metadata-driven (spec 0004): resolves the resource's
 * `ResourcePermissions` before rendering — edit mode from the loaded
 * instance detail, create mode from `GET /meta/project-statuses` — then
 * hands off to `ProjectStatusFormBody`, which reads every field from that
 * context via `MetaField`/`useResourcePermissions()`.
 */
export function ProjectStatusForm(props: ProjectStatusFormProps) {
  const { t } = useTranslation()
  const meta = useProjectStatusFormMeta(props.mode)

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
      <ProjectStatusFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
