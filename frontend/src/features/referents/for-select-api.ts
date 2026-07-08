import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the referents for-select endpoint. */
export const REFERENTS_FOR_SELECT_RESOURCE = 'referents'

/**
 * Fetches a page of referent options from `GET /api/referents/for-select`.
 * Thin wrapper over the generic for-select fetcher, bound to the `referents`
 * resource. Items carry only `label` (name).
 */
export function fetchReferentsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(REFERENTS_FOR_SELECT_RESOURCE, params)
}

interface UseReferentsForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a referent select (single or multi): debounced server
 * search, offset pagination and `ids[]` hydration, bound to the `referents`
 * resource.
 */
export function useReferentsForSelect({
  search,
  ids,
  enabled,
}: UseReferentsForSelectOptions) {
  return useForSelect({
    resource: REFERENTS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
