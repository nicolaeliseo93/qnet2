import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the pipeline-statuses for-select endpoint. */
export const PROJECT_STATUSES_FOR_SELECT_RESOURCE = 'pipeline-statuses'

/**
 * Fetches a page of project status options from
 * `GET /api/pipeline-statuses/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `pipeline-statuses` resource. Consumed by
 * the Project/Campaign forms (spec 0023) to pick a status.
 */
export function fetchPipelineStatusesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(PROJECT_STATUSES_FOR_SELECT_RESOURCE, params)
}

interface UsePipelineStatusesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a project status single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `pipeline-statuses` resource.
 */
export function usePipelineStatusesForSelect({
  search,
  ids,
  enabled,
}: UsePipelineStatusesForSelectOptions) {
  return useForSelect({
    resource: PROJECT_STATUSES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
