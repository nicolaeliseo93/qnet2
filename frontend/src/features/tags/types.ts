/**
 * Tags CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * tags-specific — the resource and its create/update payloads. Source of
 * truth: spec 0019 frozen contract (mirrors ReferentTypes, spec 0016).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Single tag detail returned by GET/POST/PATCH /tags (envelope `data`).
 * Matches `TagResource`.
 */
export interface TagDetail {
  id: number
  name: string
  created_at: string
}

/**
 * A `TagDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /tags/{id}` (`show`). Used to
 * seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface TagDetailWithPermissions extends TagDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /tags (create). */
export interface CreateTagPayload {
  name: string
}

/**
 * Payload for PATCH /tags/{id} (partial update). Every field is optional so
 * the request only carries what actually changed.
 */
export type UpdateTagPayload = Partial<CreateTagPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `TagForm` component (mirrors `ReferentTypeFormMode`).
 */
export type TagFormMode = { type: 'create' } | { type: 'edit'; tag: TagDetailWithPermissions }
