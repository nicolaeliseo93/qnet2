import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { City, Country, Province, State } from '@/features/geo/types'

/**
 * Geo reference data layer (ADR 0010). All endpoints are authenticated and
 * return the standard envelope; each call unwraps to `data`. States, provinces
 * and cities require their parent id, mirroring the backend contract.
 */

/** Lists all countries, ordered by name. */
export async function fetchCountries(): Promise<Country[]> {
  const { data } = await apiClient.get<ApiResponse<Country[]>>('/countries')
  return data.data
}

/** Lists the states of a country, ordered by name. */
export async function fetchStates(countryId: number): Promise<State[]> {
  const { data } = await apiClient.get<ApiResponse<State[]>>('/states', {
    params: { country_id: countryId },
  })
  return data.data
}

/** Lists the provinces of a state/region, ordered by name. */
export async function fetchProvinces(stateId: number): Promise<Province[]> {
  const { data } = await apiClient.get<ApiResponse<Province[]>>('/provinces', {
    params: { state_id: stateId },
  })
  return data.data
}

/**
 * Lists one page of cities of a province (preferred, the finest level) or, for
 * countries without a province level, of a state, ordered by name (page size
 * 50). An optional `search` prefix narrows the results server-side; `offset`
 * skips already-loaded rows so the caller can scroll through provinces with
 * hundreds of comuni.
 */
export async function fetchCities(parent: {
  stateId: number
  provinceId?: number | null
  search?: string
  offset?: number
}): Promise<City[]> {
  const { stateId, provinceId, search, offset } = parent
  const { data } = await apiClient.get<ApiResponse<City[]>>('/cities', {
    params: {
      ...(provinceId != null
        ? { province_id: provinceId }
        : { state_id: stateId }),
      search: search || undefined,
      offset: offset || undefined,
    },
  })
  return data.data
}
