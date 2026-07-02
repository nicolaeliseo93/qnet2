import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the users for-select endpoint. */
export const USERS_FOR_SELECT_RESOURCE = 'users'

/**
 * Fetches a page of user options from `GET /api/users/for-select`. Thin wrapper
 * over the generic for-select fetcher, bound to the `users` resource. For users
 * the item carries `label` (name) and `subtitle` (email).
 */
export function fetchUsersForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(USERS_FOR_SELECT_RESOURCE, params)
}

interface UseUsersForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding the users multi-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `users` resource.
 */
export function useUsersForSelect({
  search,
  ids,
  enabled,
}: UseUsersForSelectOptions) {
  return useForSelect({
    resource: USERS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
