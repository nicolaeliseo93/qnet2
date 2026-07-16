import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the leads for-select endpoint (spec 0040 amendment A-1, ADR 0011). */
export const LEADS_FOR_SELECT_RESOURCE = 'leads'

/**
 * Fetches a page of lead options from `GET /api/leads/for-select`. Thin
 * wrapper over the generic for-select fetcher, bound to the `leads` resource.
 * Items carry `label` (the lead's referent name, D-3: a lead has no name of
 * its own) and `subtitle` (the linked campaign's code/name).
 */
export function fetchLeadsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(LEADS_FOR_SELECT_RESOURCE, params)
}

interface UseLeadsForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a lead single-select: debounced server search, offset
 * pagination and `ids[]` hydration, bound to the `leads` resource.
 */
export function useLeadsForSelect({ search, ids, enabled }: UseLeadsForSelectOptions) {
  return useForSelect({
    resource: LEADS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
