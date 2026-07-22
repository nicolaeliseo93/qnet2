/**
 * Centralized TanStack Query keys for the collaborative notes feature (spec
 * 0052). Every key is parametric on the host entity (`entityType`/`entityId`,
 * D-9/D-14) — the feature never bakes in a specific module.
 */
export const notesKeys = {
  /** Keyset-paginated root list of a single host record. */
  list: (entityType: string, entityId: number) => ['notes', entityType, entityId] as const,
  /**
   * Contextual mention lookup of a single host record (D-10), keyed by the
   * active search term so each search starts a fresh paginated query.
   */
  mentionable: (entityType: string, entityId: number, search: string) =>
    ['notes', entityType, entityId, 'mentionable-users', { search }] as const,
}
