import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the sectors for-select endpoint. */
export const SECTORS_FOR_SELECT_RESOURCE = 'sectors'

/**
 * Fetches a page of sector options from `GET /api/sectors/for-select`.
 * Thin wrapper over the generic for-select fetcher, bound to the `sectors`
 * resource. Items carry only `label` (name).
 */
export function fetchSectorsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(SECTORS_FOR_SELECT_RESOURCE, params)
}

interface UseSectorsForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding the sectors multiselect: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `sectors` resource.
 */
export function useSectorsForSelect({
  search,
  ids,
  enabled,
}: UseSectorsForSelectOptions) {
  return useForSelect({
    resource: SECTORS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
