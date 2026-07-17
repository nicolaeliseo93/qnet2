import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { VatRateFormMode } from '@/features/vat-rates/types'

/** Metadata-loading state driving what `VatRateForm` renders. */
export type VatRateFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.vatRate.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/vat-rates`) once.
 */
export function useVatRateFormMeta(mode: VatRateFormMode): VatRateFormMetaState {
  const metaQuery = useResourceMeta('vat-rates', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.vatRate.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
