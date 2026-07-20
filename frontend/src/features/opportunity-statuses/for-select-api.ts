import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the opportunity-statuses for-select endpoint. */
export const OPPORTUNITY_STATUSES_FOR_SELECT_RESOURCE = 'opportunity-statuses'

/**
 * Fetches a page of opportunity status options from
 * `GET /api/opportunity-statuses/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `opportunity-statuses` resource. Consumed
 * by the Opportunity form (spec 0043) to pick the mandatory opportunity
 * status.
 */
export function fetchOpportunityStatusesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(OPPORTUNITY_STATUSES_FOR_SELECT_RESOURCE, params)
}

interface UseOpportunityStatusesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding an opportunity status single-select: debounced
 * server search, offset pagination and `ids[]` hydration, bound to the
 * `opportunity-statuses` resource.
 */
export function useOpportunityStatusesForSelect({
  search,
  ids,
  enabled,
}: UseOpportunityStatusesForSelectOptions) {
  return useForSelect({
    resource: OPPORTUNITY_STATUSES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
