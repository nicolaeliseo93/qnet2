/**
 * Project statuses CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely project-statuses-specific — the resource and its create/
 * update payloads. Source of truth: spec 0023 frozen `data_contract`
 * (mirrors Sources, spec 0018).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Single project status detail returned by GET/POST/PATCH
 * /project-statuses (envelope `data`). Matches `ProjectStatusResource`.
 */
export interface ProjectStatusDetail {
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
 * A `ProjectStatusDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /project-statuses/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface ProjectStatusDetailWithPermissions extends ProjectStatusDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /project-statuses (create). */
export interface CreateProjectStatusPayload {
  name: string
  color?: string | null
  sort_order?: number
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /project-statuses/{id} (partial update). Every field is
 * optional so the request only carries what actually changed.
 */
export type UpdateProjectStatusPayload = Partial<CreateProjectStatusPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `ProjectStatusForm` component (mirrors `SourceFormMode`).
 */
export type ProjectStatusFormMode =
  | { type: 'create' }
  | { type: 'edit'; projectStatus: ProjectStatusDetailWithPermissions }
