/**
 * Attributes CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely attributes-specific — the resource, its options and its
 * create/update payloads. Source of truth: spec 0017 frozen `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/** Dynamic attribute data types (backend `App\Enums\AttributeType`). */
export const ATTRIBUTE_DATA_TYPES = ['STRING', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'ENUM'] as const

export type AttributeDataType = (typeof ATTRIBUTE_DATA_TYPES)[number]

/** A single ENUM option, as persisted server-side. */
export interface AttributeOption {
  id: number
  value: string
  label: string
  sort_order: number
}

/**
 * Single attribute detail returned by GET/POST/PATCH /attributes (envelope
 * `data`). Matches `AttributeResource`.
 */
export interface AttributeDetail {
  id: number
  code: string
  name: string
  data_type: AttributeDataType
  options: AttributeOption[]
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * An `AttributeDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /attributes/{id}` (`show`). Used
 * to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface AttributeDetailWithPermissions extends AttributeDetail {
  permissions: ResourcePermissions
}

/** A single ENUM option as sent to the backend (no `id`: full-replace). */
export interface AttributeOptionInput {
  value: string
  label: string
  sort_order?: number
}

/**
 * Payload for POST /attributes (create). `options` is required/non-empty only
 * when `data_type` is `ENUM`; ignored otherwise (spec AC-003).
 */
export interface CreateAttributePayload {
  code: string
  name: string
  data_type: AttributeDataType
  options?: AttributeOptionInput[]
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /attributes/{id} (partial update). `options`, when sent,
 * is a full-replace of the attribute's option set.
 */
export type UpdateAttributePayload = Partial<CreateAttributePayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `AttributeForm` component (mirrors `ReferentTypeFormMode`).
 */
export type AttributeFormMode =
  | { type: 'create' }
  | { type: 'edit'; attribute: AttributeDetailWithPermissions }
