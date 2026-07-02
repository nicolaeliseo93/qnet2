import { useQuery } from '@tanstack/react-query'
import {
  fetchCities,
  fetchCountries,
  fetchProvinces,
  fetchStates,
} from '@/features/geo/api'
import { geoKeys } from '@/features/geo/query-keys'

/**
 * Geo reference data hooks (ADR 0010). Countries load eagerly; states, provinces
 * and cities are DEPENDENT queries gated on their parent id, so no request fires
 * until the parent has been chosen. The lists are stable lookup data, so they
 * never go stale within a session.
 */

/** Loads all countries. */
export function useCountries() {
  return useQuery({
    queryKey: geoKeys.countries,
    queryFn: fetchCountries,
    staleTime: Infinity,
  })
}

/** Loads the states of a country; disabled until `countryId` is set. */
export function useStates(countryId: number | null) {
  return useQuery({
    queryKey: geoKeys.states(countryId ?? 0),
    queryFn: () => fetchStates(countryId as number),
    enabled: countryId != null,
    staleTime: Infinity,
  })
}

/** Loads the provinces of a state; disabled until `stateId` is set. */
export function useProvinces(stateId: number | null) {
  return useQuery({
    queryKey: geoKeys.provinces(stateId ?? 0),
    queryFn: () => fetchProvinces(stateId as number),
    enabled: stateId != null,
    staleTime: Infinity,
  })
}

/**
 * Loads the cities of a province when one is chosen, otherwise of the state
 * (countries without a province level). Disabled until `stateId` is set.
 */
export function useCities(
  stateId: number | null,
  provinceId?: number | null,
  search?: string,
) {
  return useQuery({
    queryKey: geoKeys.cities(stateId ?? 0, provinceId ?? 0, search ?? ''),
    queryFn: () =>
      fetchCities({ stateId: stateId as number, provinceId, search }),
    enabled: stateId != null,
    staleTime: Infinity,
  })
}
