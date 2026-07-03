import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useBusinessFunctionFormMeta } from '@/features/business-functions/use-business-function-form-meta'
import { BusinessFunctionFormBody } from '@/features/business-functions/business-function-form-body'
import type {
  BusinessFunctionDetail,
  BusinessFunctionFormMode,
} from '@/features/business-functions/types'

interface BusinessFunctionFormProps {
  mode: BusinessFunctionFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (businessFunction: BusinessFunctionDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a business
 * function. Metadata-driven (spec 0004): resolves the resource's
 * `ResourcePermissions` before rendering — edit mode from the loaded instance
 * detail, create mode from `GET /meta/business-functions` — then hands off to
 * `BusinessFunctionFormBody`, which reads every field from that context via
 * `MetaField`/`useResourcePermissions()`.
 */
export function BusinessFunctionForm(props: BusinessFunctionFormProps) {
  const { t } = useTranslation()
  const meta = useBusinessFunctionFormMeta(props.mode)

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
      <BusinessFunctionFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
