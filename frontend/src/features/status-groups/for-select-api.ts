import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the status-groups for-select endpoint. */
export const STATUS_GROUPS_FOR_SELECT_RESOURCE = 'status-groups'

/**
 * Fetches a page of status group options from
 * `GET /api/status-groups/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `status-groups` resource. Consumed by the
 * pipeline-statuses and lead-statuses forms (spec 0039) to pick the optional
 * status group.
 */
export function fetchStatusGroupsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(STATUS_GROUPS_FOR_SELECT_RESOURCE, params)
}

interface UseStatusGroupsForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a status group single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `status-groups` resource.
 */
export function useStatusGroupsForSelect({
  search,
  ids,
  enabled,
}: UseStatusGroupsForSelectOptions) {
  return useForSelect({
    resource: STATUS_GROUPS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
