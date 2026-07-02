/**
 * Reference geo data (ADR 0010). These are read-only lookup entities served by
 * the backend countries/states/provinces/cities endpoints, used to drive the
 * cascading country → state → province → city selects. They mirror the API
 * resources 1:1.
 */

/** A country (GET /api/countries). */
export interface Country {
  id: number
  name: string
  iso2: string
}

/** A state/region scoped to a country (GET /api/states). */
export interface State {
  id: number
  name: string
  country_id: number
}

/** A province scoped to a state/region (GET /api/provinces). */
export interface Province {
  id: number
  name: string
  state_id: number
}

/**
 * A city scoped to a state and, when the country has a province level, to a
 * province (GET /api/cities). `province_id` is null for countries without
 * provinces.
 */
export interface City {
  id: number
  name: string
  state_id: number
  province_id: number | null
}
