import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/**
 * Resource segment for the geo `states` ("Regione") for-select endpoint
 * (spec 0023). Distinct from `fetchStates(countryId)` in `features/geo/api.ts`
 * (the cascading country → state picker): this is a free-search, no-parent
 * lookup for modules that reference a region directly (Projects/Campaigns).
 * Items carry `subtitle` = the state's country name.
 */
export const STATES_FOR_SELECT_RESOURCE = 'states'

/** Fetches a page of state/region options from `GET /api/states/for-select`. */
export function fetchStatesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(STATES_FOR_SELECT_RESOURCE, params)
}

interface UseStatesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a state/region single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the `states`
 * resource.
 */
export function useStatesForSelect({ search, ids, enabled }: UseStatesForSelectOptions) {
  return useForSelect({
    resource: STATES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
