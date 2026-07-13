/**
 * Parses the `:id` route param of an entity detail/edit page. Anything that is
 * not a positive integer (`/registries/abc`) is not an entity id, so the page
 * renders the not-found route instead of firing a request that can only 404.
 */
export function parseEntityId(param: string | undefined): number | null {
  if (!param || !/^\d+$/.test(param)) return null
  const id = Number(param)
  return Number.isSafeInteger(id) && id > 0 ? id : null
}
