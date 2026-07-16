import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the company-sites for-select endpoint (spec 0040, ADR 0011). */
export const COMPANY_SITES_FOR_SELECT_RESOURCE = 'company-sites'

/**
 * Fetches a page of company-site options from
 * `GET /api/company-sites/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `company-sites` resource. Items carry
 * `label` (name) and `subtitle` (company denomination). Accepts the optional
 * `company_id` dependency param (spec 0040 BR-4) via the generic `params`.
 */
export function fetchCompanySitesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(COMPANY_SITES_FOR_SELECT_RESOURCE, params)
}

interface UseCompanySitesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a company-site single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `company-sites` resource.
 */
export function useCompanySitesForSelect({
  search,
  ids,
  enabled,
}: UseCompanySitesForSelectOptions) {
  return useForSelect({
    resource: COMPANY_SITES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
