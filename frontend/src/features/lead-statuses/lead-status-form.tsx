import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useLeadStatusFormMeta } from '@/features/lead-statuses/use-lead-status-form-meta'
import { LeadStatusFormBody } from '@/features/lead-statuses/lead-status-form-body'
import type { LeadStatusDetail, LeadStatusFormMode } from '@/features/lead-statuses/types'

interface LeadStatusFormProps {
  mode: LeadStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (leadStatus: LeadStatusDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a lead status.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/lead-statuses` — then hands off to `LeadStatusFormBody`,
 * which reads every field from that context via
 * `MetaField`/`useResourcePermissions()`.
 */
export function LeadStatusForm(props: LeadStatusFormProps) {
  const { t } = useTranslation()
  const meta = useLeadStatusFormMeta(props.mode)

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
      <LeadStatusFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
