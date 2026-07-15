/**
 * Project statuses CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely pipeline-statuses-specific — the resource and its create/
 * update payloads. Source of truth: spec 0023 frozen `data_contract`
 * (mirrors Sources, spec 0018).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Single project status detail returned by GET/POST/PATCH
 * /pipeline-statuses (envelope `data`). Matches `PipelineStatusResource`.
 */
export interface PipelineStatusDetail {
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
 * A `PipelineStatusDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /pipeline-statuses/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface PipelineStatusDetailWithPermissions extends PipelineStatusDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /pipeline-statuses (create). */
export interface CreatePipelineStatusPayload {
  name: string
  color?: string | null
  sort_order?: number
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /pipeline-statuses/{id} (partial update). Every field is
 * optional so the request only carries what actually changed.
 */
export type UpdatePipelineStatusPayload = Partial<CreatePipelineStatusPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `PipelineStatusForm` component (mirrors `SourceFormMode`).
 */
export type PipelineStatusFormMode =
  | { type: 'create' }
  | { type: 'edit'; pipelineStatus: PipelineStatusDetailWithPermissions }
