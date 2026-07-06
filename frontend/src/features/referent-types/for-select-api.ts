import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the referent-types for-select endpoint. */
export const REFERENT_TYPES_FOR_SELECT_RESOURCE = 'referent-types'

/**
 * Fetches a page of referent-type options from
 * `GET /api/referent-types/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `referent-types` resource. Items carry
 * only `label` (name).
 */
export function fetchReferentTypesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(REFERENT_TYPES_FOR_SELECT_RESOURCE, params)
}

interface UseReferentTypesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a referent-type single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `referent-types` resource.
 */
export function useReferentTypesForSelect({
  search,
  ids,
  enabled,
}: UseReferentTypesForSelectOptions) {
  return useForSelect({
    resource: REFERENT_TYPES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
