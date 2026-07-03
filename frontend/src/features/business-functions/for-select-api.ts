import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the business-functions for-select endpoint. */
export const BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE = 'business-functions'

/**
 * Fetches a page of business-function options from
 * `GET /api/business-functions/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `business-functions` resource. Items carry
 * only `label` (name).
 */
export function fetchBusinessFunctionsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE, params)
}

interface UseBusinessFunctionsForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a business-function single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `business-functions` resource.
 */
export function useBusinessFunctionsForSelect({
  search,
  ids,
  enabled,
}: UseBusinessFunctionsForSelectOptions) {
  return useForSelect({
    resource: BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
