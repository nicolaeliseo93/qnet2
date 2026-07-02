/**
 * Query keys for the geo reference data. The state/province/city keys are scoped
 * to their parent id (and the city key to its optional search) so dependent
 * queries cache and invalidate per parent. The city key is scoped to BOTH the
 * province and the state id so it never reuses results across parents (province
 * id is 0 when filtering by state, e.g. countries without a province level).
 */
export const geoKeys = {
  all: ['geo'] as const,
  countries: ['geo', 'countries'] as const,
  states: (countryId: number) => ['geo', 'states', countryId] as const,
  provinces: (stateId: number) => ['geo', 'provinces', stateId] as const,
  cities: (stateId: number, provinceId: number, search: string) =>
    ['geo', 'cities', stateId, provinceId, search] as const,
}
