import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useCompanySiteFormMeta } from '@/features/company-sites/use-company-site-form-meta'
import { CompanySiteFormBody } from '@/features/company-sites/company-site-form-body'
import type {
  CompanySiteDetail,
  CompanySiteDetailWithPermissions,
} from '@/features/company-sites/types'

export type CompanySiteFormMode =
  | { type: 'create' }
  | { type: 'edit'; companySite: CompanySiteDetailWithPermissions }

interface CompanySiteFormProps {
  mode: CompanySiteFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (companySite: CompanySiteDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
  /**
   * EDIT mode only: called after an immediate logo upload/remove or a
   * set-default call succeeds, so the caller can refresh the grid without
   * closing the form.
   */
  onSiteChange?: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a company site.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/company-sites` — then hands off to `CompanySiteFormBody`,
 * which reads every field/action from that context via
 * `MetaField`/`useResourcePermissions()`.
 */
export function CompanySiteForm(props: CompanySiteFormProps) {
  const { t } = useTranslation()
  const meta = useCompanySiteFormMeta(props.mode)

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
      <CompanySiteFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
