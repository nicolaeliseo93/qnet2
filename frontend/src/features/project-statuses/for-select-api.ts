import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the project-statuses for-select endpoint. */
export const PROJECT_STATUSES_FOR_SELECT_RESOURCE = 'project-statuses'

/**
 * Fetches a page of project status options from
 * `GET /api/project-statuses/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `project-statuses` resource. Consumed by
 * the Project/Campaign forms (spec 0023) to pick a status.
 */
export function fetchProjectStatusesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(PROJECT_STATUSES_FOR_SELECT_RESOURCE, params)
}

interface UseProjectStatusesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a project status single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `project-statuses` resource.
 */
export function useProjectStatusesForSelect({
  search,
  ids,
  enabled,
}: UseProjectStatusesForSelectOptions) {
  return useForSelect({
    resource: PROJECT_STATUSES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
