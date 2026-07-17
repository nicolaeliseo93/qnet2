import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useVatRateFormMeta } from '@/features/vat-rates/use-vat-rate-form-meta'
import { VatRateFormBody } from '@/features/vat-rates/vat-rate-form-body'
import type { VatRateDetail, VatRateFormMode } from '@/features/vat-rates/types'

interface VatRateFormProps {
  mode: VatRateFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (vatRate: VatRateDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a VAT rate.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/vat-rates` — then hands off to `VatRateFormBody`, which
 * reads every field from that context via `MetaField`/`useResourcePermissions()`.
 */
export function VatRateForm(props: VatRateFormProps) {
  const { t } = useTranslation()
  const meta = useVatRateFormMeta(props.mode)

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
      <VatRateFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
