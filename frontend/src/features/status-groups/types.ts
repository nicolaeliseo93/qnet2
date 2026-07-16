/**
 * Status groups CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely status-groups-specific — the resource and its create/update
 * payloads. Source of truth: spec 0039 frozen `data_contract` (mirrors
 * Lead Statuses, spec 0029).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Single status group detail returned by GET/POST/PATCH /status-groups
 * (envelope `data`). Matches `StatusGroupResource`.
 */
export interface StatusGroupDetail {
  id: number
  name: string
  /** Palette token (e.g. "blue"), or null when unset. */
  color: string | null
  sort_order: number
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `StatusGroupDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /status-groups/{id}` (`show`).
 * Used to seed the edit form's `ResourcePermissionsProvider` without a
 * second request.
 */
export interface StatusGroupDetailWithPermissions extends StatusGroupDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /status-groups (create). */
export interface CreateStatusGroupPayload {
  name: string
  color?: string | null
  sort_order?: number
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /status-groups/{id} (partial update). Every field is
 * optional so the request only carries what actually changed.
 */
export type UpdateStatusGroupPayload = Partial<CreateStatusGroupPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `StatusGroupForm` component (mirrors `LeadStatusFormMode`).
 */
export type StatusGroupFormMode =
  | { type: 'create' }
  | { type: 'edit'; statusGroup: StatusGroupDetailWithPermissions }
