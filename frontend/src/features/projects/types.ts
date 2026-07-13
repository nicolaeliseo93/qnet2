/**
 * Projects CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * projects-specific. Source of truth: spec 0023 frozen `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/** Hydrated projection of a plain `{id, name}` relation (registry/source/business_function/state/product_category/partner). */
export interface ProjectRelationRef {
  id: number
  name: string
}

/** Hydrated projection of the project's status, carrying its display color token. */
export interface ProjectStatusRef {
  id: number
  name: string
  color: string | null
}

/**
 * Single project detail returned by GET/POST/PATCH /projects (envelope
 * `data`). Matches `ProjectResource`. `total_budget`/`allocated_budget`/
 * `remaining_budget` are `decimal(15,2)` columns cast `decimal:2` server-side
 * (Laravel serializes a decimal cast as a numeric STRING, never a JS number —
 * mirrors the `total_budget`/`allocated_budget`/`remaining_budget` shape
 * frozen for the `/projects/for-select` `meta` block in the same contract).
 */
export interface ProjectDetail {
  id: number
  code: string
  name: string
  description: string | null
  registry_id: number | null
  registry: ProjectRelationRef | null
  project_status_id: number
  project_status: ProjectStatusRef
  source_id: number | null
  source: ProjectRelationRef | null
  business_function_id: number | null
  business_function: ProjectRelationRef | null
  state_id: number | null
  state: ProjectRelationRef | null
  product_category_id: number | null
  product_category: ProjectRelationRef | null
  partner_id: number | null
  partner: ProjectRelationRef | null
  start_date: string | null
  end_date: string | null
  total_budget: string | null
  target_lead: number | null
  /** Sum of the linked campaigns' `total_budget` (BR-7). */
  allocated_budget: string
  /** `total_budget - allocated_budget`, `null` when `total_budget` is unset (BR-7). */
  remaining_budget: string | null
  /** Number of campaigns linked to this project (drives the delete guard, BR-5/AC-016). */
  campaigns_count: number
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `ProjectDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /projects/{id}`. Used to seed the
 * edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface ProjectDetailWithPermissions extends ProjectDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /projects (create). `code` is NEVER part of this shape
 * (BR-1): it is server-generated and any client-submitted value is ignored.
 */
export interface CreateProjectPayload {
  name: string
  project_status_id: number
  description?: string | null
  registry_id?: number | null
  source_id?: number | null
  business_function_id?: number | null
  state_id?: number | null
  product_category_id?: number | null
  partner_id?: number | null
  start_date?: string | null
  end_date?: string | null
  total_budget?: number | null
  target_lead?: number | null
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/** Payload for PATCH /projects/{id} (partial update). Every field is optional (sparse diff). */
export type UpdateProjectPayload = Partial<CreateProjectPayload>

/** Discriminated form mode shared by the form hook/meta-resolver and `ProjectForm`. */
export type ProjectFormMode =
  | { type: 'create' }
  | { type: 'edit'; project: ProjectDetailWithPermissions }
