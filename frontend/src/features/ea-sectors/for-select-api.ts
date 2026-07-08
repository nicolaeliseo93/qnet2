import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the ea-sectors for-select endpoint. */
export const EA_SECTORS_FOR_SELECT_RESOURCE = 'ea-sectors'

/**
 * Fetches a page of EA sector options from `GET /api/ea-sectors/for-select`.
 * Thin wrapper over the generic for-select fetcher, bound to the `ea-sectors`
 * resource. Items carry only `label` (name).
 */
export function fetchEaSectorsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(EA_SECTORS_FOR_SELECT_RESOURCE, params)
}

interface UseEaSectorsForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding the EA sectors multiselect: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `ea-sectors` resource.
 */
export function useEaSectorsForSelect({
  search,
  ids,
  enabled,
}: UseEaSectorsForSelectOptions) {
  return useForSelect({
    resource: EA_SECTORS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
