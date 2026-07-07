import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the sources for-select endpoint. */
export const SOURCES_FOR_SELECT_RESOURCE = 'sources'

/**
 * Fetches a page of source options from `GET /api/sources/for-select`. Thin
 * wrapper over the generic for-select fetcher, bound to the `sources`
 * resource. Items carry only `label` (name).
 */
export function fetchSourcesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(SOURCES_FOR_SELECT_RESOURCE, params)
}

interface UseSourcesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a source single-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `sources` resource.
 */
export function useSourcesForSelect({ search, ids, enabled }: UseSourcesForSelectOptions) {
  return useForSelect({
    resource: SOURCES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
