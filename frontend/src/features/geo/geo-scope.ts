/**
 * Derived geo scope (spec 0027 D-2): the finest non-null level of a
 * country/state/province/city tuple. The derivation itself lives ONLY
 * server-side (`App\Enums\GeoScopeLevel`) and is emitted as `geo_scope` by
 * every resource that carries geo (projects, campaigns); this module never
 * re-derives that rule, it only maps an already-known scope to its i18n
 * label key and, where the API does not also emit a ready-made label (unlike
 * `ProjectCardResource.geo_label`), to the matching place name out of an
 * already-loaded tuple of `{id, name}` refs.
 */
export type GeoScope = 'country' | 'state' | 'province' | 'city'

const GEO_SCOPE_LABEL_KEYS: Record<GeoScope, string> = {
  country: 'geo.scope.country',
  state: 'geo.scope.state',
  province: 'geo.scope.province',
  city: 'geo.scope.city',
}

/** i18n key for the scope badge label (Nazionale/Regionale/Provinciale/Cittadino). */
export function geoScopeLabelKey(scope: GeoScope): string {
  return GEO_SCOPE_LABEL_KEYS[scope]
}

/** A hydrated `{name}` projection at one geo level, or absent. */
interface GeoScopeNameRef {
  name: string
}

/** The four geo level refs a scope is picked out of. */
export interface GeoScopeNames {
  country: GeoScopeNameRef | null
  state: GeoScopeNameRef | null
  province: GeoScopeNameRef | null
  city: GeoScopeNameRef | null
}

/**
 * Picks the display name matching an already-derived scope out of the loaded
 * level refs, e.g. for `ProjectDetail`/`CampaignResource`, which carry the
 * full tuple but no ready-made label (unlike the project card's `geo_label`).
 */
export function geoScopePlaceName(scope: GeoScope, names: GeoScopeNames): string | null {
  switch (scope) {
    case 'city':
      return names.city?.name ?? null
    case 'province':
      return names.province?.name ?? null
    case 'state':
      return names.state?.name ?? null
    case 'country':
      return names.country?.name ?? null
    default:
      return null
  }
}
