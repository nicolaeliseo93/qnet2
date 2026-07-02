import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the roles for-select endpoint. */
export const ROLES_FOR_SELECT_RESOURCE = 'roles'

/**
 * Fetches a page of role options from `GET /api/roles/for-select`. Thin wrapper
 * over the generic for-select fetcher, bound to the `roles` resource. For roles
 * the item carries only `label` (name); options are actor-scoped server-side to
 * the roles the actor may assign (a non super-admin never sees `super-admin`).
 */
export function fetchRolesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(ROLES_FOR_SELECT_RESOURCE, params)
}

interface UseRolesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding the roles multi-select: debounced server search, offset
 * pagination and `ids[]` hydration, bound to the `roles` resource.
 */
export function useRolesForSelect({
  search,
  ids,
  enabled,
}: UseRolesForSelectOptions) {
  return useForSelect({
    resource: ROLES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
