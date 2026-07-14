import type { CampaignRelationRef } from '@/features/campaigns/types'

/**
 * The four geo cascade levels shared by projects and campaigns (spec 0027
 * D-1/BR-4/BR-5). Kept as a plain literal tuple here, decoupled from
 * `features/geo/geo-scope.ts`'s `GeoScope` (owned by a different lane): the
 * two are structurally identical string unions
 * (`'country'|'state'|'province'|'city'`), so values flow between them
 * without a cast (e.g. assigning `CampaignDetail.geo_locked_levels` into the
 * form's own locked-levels field).
 */
export const GEO_LEVELS = ['country', 'state', 'province', 'city'] as const

export type CampaignGeoLevel = (typeof GEO_LEVELS)[number]

/** The campaign form field name backing each geo level. */
export type GeoFieldName = 'country_id' | 'state_id' | 'province_id' | 'city_id'

export const GEO_LEVEL_FIELDS: Record<CampaignGeoLevel, GeoFieldName> = {
  country: 'country_id',
  state: 'state_id',
  province: 'province_id',
  city: 'city_id',
}

/**
 * The linked project's own geo block, as expected inside the `for-select`
 * `meta` object (spec 0027 D-5). Dependency on another lane: `ProjectForSelectMeta`
 * (`features/projects/for-select-api.ts`, not owned by this lane) is expected
 * to gain an optional `geo` field of this shape — declared locally so this
 * lane can build and test against the frozen contract without waiting for the
 * other lane to land it (see handoff).
 */
export interface ProjectGeoMeta {
  country: CampaignRelationRef | null
  state: CampaignRelationRef | null
  province: CampaignRelationRef | null
  city: CampaignRelationRef | null
}

/** Hoisted empty default: never inline `?? {...}`, it would create a new reference every render. */
export const EMPTY_PROJECT_GEO: ProjectGeoMeta = {
  country: null,
  state: null,
  province: null,
  city: null,
}

/** Which levels a project's geo block fills, in cascade order (BR-5: those become the campaign's locked levels). */
export function lockedLevelsFromProjectGeo(geo: ProjectGeoMeta): CampaignGeoLevel[] {
  return GEO_LEVELS.filter((level) => geo[level] !== null)
}
