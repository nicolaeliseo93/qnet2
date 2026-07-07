import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the tags for-select endpoint. */
export const TAGS_FOR_SELECT_RESOURCE = 'tags'

/**
 * Fetches a page of tag options from `GET /api/tags/for-select`. Thin
 * wrapper over the generic for-select fetcher, bound to the `tags` resource.
 * Items carry only `label` (name).
 */
export function fetchTagsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(TAGS_FOR_SELECT_RESOURCE, params)
}

interface UseTagsForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a tag multi-select: debounced server search, offset
 * pagination and `ids[]` hydration, bound to the `tags` resource.
 */
export function useTagsForSelect({ search, ids, enabled }: UseTagsForSelectOptions) {
  return useForSelect({
    resource: TAGS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
