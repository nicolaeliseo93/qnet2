import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the vat-rates for-select endpoint. */
export const VAT_RATES_FOR_SELECT_RESOURCE = 'vat-rates'

/**
 * Fetches a page of VAT rate options from `GET /api/vat-rates/for-select`.
 * Thin wrapper over the generic for-select fetcher, bound to the
 * `vat-rates` resource. Items carry only `label` (name).
 */
export function fetchVatRatesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(VAT_RATES_FOR_SELECT_RESOURCE, params)
}

interface UseVatRatesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a VAT rate single-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `vat-rates` resource.
 */
export function useVatRatesForSelect({ search, ids, enabled }: UseVatRatesForSelectOptions) {
  return useForSelect({
    resource: VAT_RATES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
