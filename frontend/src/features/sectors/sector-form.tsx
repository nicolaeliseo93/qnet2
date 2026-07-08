import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useSectorFormMeta } from '@/features/sectors/use-sector-form-meta'
import { SectorFormBody } from '@/features/sectors/sector-form-body'
import type { SectorDetail, SectorFormMode } from '@/features/sectors/types'

interface SectorFormProps {
  mode: SectorFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (sector: SectorDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a sector.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/sectors` — then hands off to `SectorFormBody`.
 */
export function SectorForm(props: SectorFormProps) {
  const { t } = useTranslation()
  const meta = useSectorFormMeta(props.mode)

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
      <SectorFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
