/**
 * Referent Types CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely referent-types-specific — the resource and its create/update
 * payloads. Source of truth: spec 0016 frozen `data_contract` (module A).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Single referent-type detail returned by GET/POST/PATCH
 * /referent-types (envelope `data`). Matches `ReferentTypeResource`.
 */
export interface ReferentTypeDetail {
  id: number
  name: string
  created_at: string
}

/**
 * A `ReferentTypeDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /referent-types/{id}` (`show`).
 * Used to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface ReferentTypeDetailWithPermissions extends ReferentTypeDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /referent-types (create). */
export interface CreateReferentTypePayload {
  name: string
}

/**
 * Payload for PATCH /referent-types/{id} (partial update). Every field is
 * optional so the request only carries what actually changed.
 */
export type UpdateReferentTypePayload = Partial<CreateReferentTypePayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `ReferentTypeForm` component (mirrors `BusinessFunctionFormMode`).
 */
export type ReferentTypeFormMode =
  | { type: 'create' }
  | { type: 'edit'; referentType: ReferentTypeDetailWithPermissions }
