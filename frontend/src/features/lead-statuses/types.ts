/**
 * Lead statuses CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely lead-statuses-specific — the resource and its create/update
 * payloads. Source of truth: spec 0029 frozen `data_contract` (mirrors
 * Project Statuses, spec 0023).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Single lead status detail returned by GET/POST/PATCH /lead-statuses
 * (envelope `data`). Matches `LeadStatusResource`.
 */
export interface LeadStatusDetail {
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
 * A `LeadStatusDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /lead-statuses/{id}` (`show`).
 * Used to seed the edit form's `ResourcePermissionsProvider` without a
 * second request.
 */
export interface LeadStatusDetailWithPermissions extends LeadStatusDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /lead-statuses (create). */
export interface CreateLeadStatusPayload {
  name: string
  color?: string | null
  sort_order?: number
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /lead-statuses/{id} (partial update). Every field is
 * optional so the request only carries what actually changed.
 */
export type UpdateLeadStatusPayload = Partial<CreateLeadStatusPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `LeadStatusForm` component (mirrors `PipelineStatusFormMode`).
 */
export type LeadStatusFormMode =
  | { type: 'create' }
  | { type: 'edit'; leadStatus: LeadStatusDetailWithPermissions }
