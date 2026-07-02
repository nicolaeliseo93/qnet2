/**
 * Query keys for the reusable for-select infinite queries. Keyed by resource and
 * the active search term so each entity's option list caches independently and
 * a search change starts a fresh paginated query. The selected `ids` are NOT
 * part of the key: they only hydrate the first page and must not fragment the
 * cache per selection.
 */
export const forSelectKeys = {
  all: ['for-select'] as const,
  resource: (resource: string) => ['for-select', resource] as const,
  list: (resource: string, search: string) =>
    ['for-select', resource, { search }] as const,
}
