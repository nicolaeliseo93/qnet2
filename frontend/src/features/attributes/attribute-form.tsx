import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useAttributeFormMeta } from '@/features/attributes/use-attribute-form-meta'
import { AttributeFormBody } from '@/features/attributes/attribute-form-body'
import type { AttributeDetail, AttributeFormMode } from '@/features/attributes/types'

interface AttributeFormProps {
  mode: AttributeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (attribute: AttributeDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing an attribute.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/attributes` — then hands off to `AttributeFormBody`, which
 * reads every field from that context via `MetaField`/`useResourcePermissions()`.
 */
export function AttributeForm(props: AttributeFormProps) {
  const { t } = useTranslation()
  const meta = useAttributeFormMeta(props.mode)

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
      <AttributeFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
