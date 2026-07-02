/**
 * Roles CRUD types. The generic table types (columns/filters/actions/rows) live
 * in `features/table/types.ts`; this file holds only what is genuinely
 * roles-specific — the role resource and its create/update payloads.
 * Source of truth: the Roles CRUD API contract (mirrors UserResource shape).
 */

/**
 * Single role detail returned by GET/POST/PATCH /roles (envelope `data`).
 * Matches RoleResource: the role plus the flat list of its permission names.
 */
export interface RoleDetail {
  id: number
  name: string
  permissions: string[]
  created_at: string | null
  /**
   * Ids of the users currently assigned to this role, emitted by `RoleResource`
   * (`[]` when the role has no members). Drives edit-mode hydration so the form
   * pre-selects current members. Typed optionally to stay defensive against
   * older responses.
   */
  users?: number[]
}

/** Payload for POST /roles (create). */
export interface CreateRolePayload {
  name: string
  permissions?: string[]
  /** Initial member ids. Omit to leave membership untouched. */
  users?: number[]
}

/**
 * Payload for PATCH /roles/{id} (partial update). Every field is optional so the
 * request only carries what actually changed. `users`: omit → membership
 * untouched; `[]` → remove all members; array → set membership.
 */
export interface UpdateRolePayload {
  name?: string
  permissions?: string[]
  users?: number[]
}
