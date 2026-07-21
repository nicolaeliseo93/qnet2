import {
  keepPreviousData,
  useInfiniteQuery,
  useQuery,
} from '@tanstack/react-query'
import {
  fetchCities,
  fetchCountries,
  fetchProvinces,
  fetchStates,
} from '@/features/geo/api'
import { geoKeys } from '@/features/geo/query-keys'

/** Server page size for the city lookup; mirrors the backend result cap. */
export const CITY_PAGE_SIZE = 50

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
 * (countries without a province level), one page at a time for infinite scroll.
 * Enabled once a `stateId` is set OR — for city-first selection — as soon as a
 * non-empty `search` term is typed, which does an unscoped lookup across every
 * state. Previous results are kept while a new search term is fetched, so the
 * open dropdown never unmounts (and closes) mid-typing.
 */
export function useCities(
  stateId: number | null,
  provinceId?: number | null,
  search?: string,
) {
  const hasSearch = (search ?? '').trim() !== ''

  return useInfiniteQuery({
    queryKey: geoKeys.cities(stateId ?? 0, provinceId ?? 0, search ?? ''),
    queryFn: ({ pageParam }) =>
      fetchCities({
        stateId,
        provinceId,
        search,
        offset: pageParam,
      }),
    enabled: stateId != null || hasSearch,
    staleTime: Infinity,
    initialPageParam: 0,
    // A full page means there may be more; a short page is the last one.
    getNextPageParam: (lastPage, allPages) =>
      lastPage.length === CITY_PAGE_SIZE
        ? allPages.length * CITY_PAGE_SIZE
        : undefined,
    placeholderData: keepPreviousData,
  })
}
