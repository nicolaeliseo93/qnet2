import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the registries for-select endpoint (spec 0023). */
export const REGISTRIES_FOR_SELECT_RESOURCE = 'registries'

/**
 * Fetches a page of registry options from `GET /api/registries/for-select`.
 * Thin wrapper over the generic for-select fetcher, bound to the `registries`
 * resource. Items carry only `label` (name).
 */
export function fetchRegistriesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(REGISTRIES_FOR_SELECT_RESOURCE, params)
}

interface UseRegistriesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a registry single-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `registries` resource.
 */
export function useRegistriesForSelect({ search, ids, enabled }: UseRegistriesForSelectOptions) {
  return useForSelect({
    resource: REGISTRIES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
