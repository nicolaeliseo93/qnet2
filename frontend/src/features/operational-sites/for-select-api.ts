import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the operational-sites for-select endpoint. */
export const OPERATIONAL_SITES_FOR_SELECT_RESOURCE = 'operational-sites'

/**
 * The site's Regione, as composed by `OperationalSiteForSelectResource::meta`
 * from its primary address (directive 2026-07-21) — omitted server-side (and
 * thus absent here) when the site has no address-derived region.
 */
export interface OperationalSiteForSelectMeta {
  state_id?: number | null
  state_label?: string | null
}

/**
 * A single operational-site option as returned by
 * `GET /api/operational-sites/for-select`, carrying its Regione presentation
 * bag so the Lead form can auto-fill `state_id` when a Sede is picked
 * (`onItemChange`), without a second fetch.
 */
export interface OperationalSiteForSelectItem extends ForSelectItem {
  meta?: OperationalSiteForSelectMeta
}

/**
 * Fetches a page of operational-site options from
 * `GET /api/operational-sites/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `operational-sites` resource. Items carry
 * `label` (address line) and `subtitle` (postal code, when present).
 */
export function fetchOperationalSitesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(OPERATIONAL_SITES_FOR_SELECT_RESOURCE, params)
}

interface UseOperationalSitesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding an operational-site single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `operational-sites` resource.
 */
export function useOperationalSitesForSelect({
  search,
  ids,
  enabled,
}: UseOperationalSitesForSelectOptions) {
  return useForSelect({
    resource: OPERATIONAL_SITES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
