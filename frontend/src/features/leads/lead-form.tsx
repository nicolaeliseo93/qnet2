import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useLeadFormMeta } from '@/features/leads/use-lead-form-meta'
import { LeadFormBody } from '@/features/leads/lead-form-body'
import type { LeadDetail, LeadFormMode } from '@/features/leads/types'

interface LeadFormProps {
  mode: LeadFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (lead: LeadDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a lead.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/leads` — then hands off to `LeadFormBody`.
 */
export function LeadForm(props: LeadFormProps) {
  const { t } = useTranslation()
  const meta = useLeadFormMeta(props.mode)

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
      <LeadFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
