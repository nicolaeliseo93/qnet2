import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useEaSectorFormMeta } from '@/features/ea-sectors/use-ea-sector-form-meta'
import { EaSectorFormBody } from '@/features/ea-sectors/ea-sector-form-body'
import type { EaSectorDetail, EaSectorFormMode } from '@/features/ea-sectors/types'

interface EaSectorFormProps {
  mode: EaSectorFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (sector: EaSectorDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing an EA sector.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/ea-sectors` — then hands off to `EaSectorFormBody`.
 */
export function EaSectorForm(props: EaSectorFormProps) {
  const { t } = useTranslation()
  const meta = useEaSectorFormMeta(props.mode)

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
      <EaSectorFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
