/**
 * Campaigns CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * campaigns-specific. Source of truth: spec 0023 frozen `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ProjectRelationRef, ProjectStatusRef } from '@/features/projects/types'

/** Hydrated `{id, name}` relation shared by registry/source/partner/business_function/state/product_category (identical shape to a project's, reused rather than redeclared). */
export type CampaignRelationRef = ProjectRelationRef

/** The linked project's minimal identity, as exposed by `CampaignResource.project`. */
export interface CampaignProjectRef {
  id: number
  code: string
  name: string
}

/**
 * Single campaign detail returned by GET/POST/PATCH /campaigns (envelope
 * `data`). Matches `CampaignResource`. Per BR-2, when `project_id` is set the
 * 4 classification fields are NULL in DB but this shape always carries the
 * EFFECTIVE values (read through the project) plus `derived_from_project`, so
 * the form/detail never need to special-case a linked campaign's data source.
 */
export interface CampaignDetail {
  id: number
  code: string
  project_id: number | null
  project: CampaignProjectRef | null
  name: string
  description: string | null
  registry_id: number | null
  registry: CampaignRelationRef | null
  source_id: number | null
  source: CampaignRelationRef | null
  partner_id: number | null
  partner: CampaignRelationRef | null
  derived_from_project: boolean
  project_status_id: number | null
  project_status: ProjectStatusRef | null
  business_function_id: number | null
  business_function: CampaignRelationRef | null
  state_id: number | null
  state: CampaignRelationRef | null
  product_category_id: number | null
  product_category: CampaignRelationRef | null
  start_date: string | null
  end_date: string | null
  total_budget: string | null
  target_lead: number | null
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `CampaignDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /campaigns/{id}`. Used to seed the
 * edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface CampaignDetailWithPermissions extends CampaignDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /campaigns (create). `code` is NEVER part of this shape
 * (BR-1): it is server-generated and any client-submitted value is ignored.
 * The 4 classification fields are `required_if project_id == null` server-side
 * (BR-2) — enforced client-side by the Zod schema, not by this type.
 */
export interface CreateCampaignPayload {
  name: string
  project_id?: number | null
  description?: string | null
  registry_id?: number | null
  source_id?: number | null
  partner_id?: number | null
  project_status_id?: number | null
  business_function_id?: number | null
  state_id?: number | null
  product_category_id?: number | null
  start_date?: string | null
  end_date?: string | null
  total_budget?: number | null
  target_lead?: number | null
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/** Payload for PATCH /campaigns/{id} (partial update). Every field is optional (sparse diff). */
export type UpdateCampaignPayload = Partial<CreateCampaignPayload>

/** Discriminated form mode shared by the form hook/meta-resolver and `CampaignForm`. */
export type CampaignFormMode =
  | { type: 'create' }
  | { type: 'edit'; campaign: CampaignDetailWithPermissions }
