import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the campaigns for-select endpoint (spec 0024, ADR 0011). */
export const CAMPAIGNS_FOR_SELECT_RESOURCE = 'campaigns'

/**
 * Fetches a page of campaign options from `GET /api/campaigns/for-select`.
 * Thin wrapper over the generic for-select fetcher, bound to the `campaigns`
 * resource. Items carry `label` (name) and `subtitle` (code).
 */
export function fetchCampaignsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(CAMPAIGNS_FOR_SELECT_RESOURCE, params)
}

interface UseCampaignsForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a campaign single-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `campaigns` resource.
 */
export function useCampaignsForSelect({ search, ids, enabled }: UseCampaignsForSelectOptions) {
  return useForSelect({
    resource: CAMPAIGNS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
