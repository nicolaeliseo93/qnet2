import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useCustomFieldDefinitionFormMeta } from '@/features/custom-fields/use-custom-field-definition-form-meta'
import { CustomFieldDefinitionFormBody } from '@/features/custom-fields/custom-field-definition-form-body'
import type { CustomFieldDefinitionDetail, CustomFieldDefinitionFormMode } from '@/features/custom-fields/types'

interface CustomFieldDefinitionFormProps {
  mode: CustomFieldDefinitionFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (definition: CustomFieldDefinitionDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a custom field
 * definition. Metadata-driven (spec 0004): resolves the resource's
 * `ResourcePermissions` before rendering — edit mode from the loaded instance
 * detail, create mode from `GET /meta/custom-fields` — then hands off to
 * `CustomFieldDefinitionFormBody`, which reads every field from that context
 * via `MetaField`/`useResourcePermissions()` (mirrors `AttributeForm`).
 */
export function CustomFieldDefinitionForm(props: CustomFieldDefinitionFormProps) {
  const { t } = useTranslation()
  const meta = useCustomFieldDefinitionFormMeta(props.mode)

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
      <CustomFieldDefinitionFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
