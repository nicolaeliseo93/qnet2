import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the companies for-select endpoint. */
export const COMPANIES_FOR_SELECT_RESOURCE = 'companies'

/**
 * Fetches a page of company options from `GET /api/companies/for-select`.
 * Thin wrapper over the generic for-select fetcher, bound to the `companies`
 * resource. Items carry `label` (denomination) and `subtitle` (VAT number,
 * when present).
 */
export function fetchCompaniesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(COMPANIES_FOR_SELECT_RESOURCE, params)
}

interface UseCompaniesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a company single-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `companies` resource.
 */
export function useCompaniesForSelect({
  search,
  ids,
  enabled,
}: UseCompaniesForSelectOptions) {
  return useForSelect({
    resource: COMPANIES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
