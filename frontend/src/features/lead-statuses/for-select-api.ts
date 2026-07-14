import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the lead-statuses for-select endpoint. */
export const LEAD_STATUSES_FOR_SELECT_RESOURCE = 'lead-statuses'

/**
 * Fetches a page of lead status options from
 * `GET /api/lead-statuses/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `lead-statuses` resource. Consumed by the
 * Lead form (spec 0029) to pick the mandatory lead status.
 */
export function fetchLeadStatusesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(LEAD_STATUSES_FOR_SELECT_RESOURCE, params)
}

interface UseLeadStatusesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a lead status single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `lead-statuses` resource.
 */
export function useLeadStatusesForSelect({
  search,
  ids,
  enabled,
}: UseLeadStatusesForSelectOptions) {
  return useForSelect({
    resource: LEAD_STATUSES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
