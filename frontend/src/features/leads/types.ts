/**
 * Leads CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * leads-specific. Source of truth: spec 0024 frozen `data_contract`. A Lead
 * has exactly 6 fields, no `code` (D-3).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Hydrated `{id, name}` relation shared by referent/source/operator. */
export interface LeadRelationRef {
  id: number
  name: string
}

/** The linked campaign's minimal identity, as exposed by `LeadResource.campaign`. */
export interface LeadCampaignRef {
  id: number
  code: string
  name: string
}

/**
 * The linked operational site's identity, as exposed by `LeadResource.operational_site`.
 * `operational_sites` has no `name` column (BR-3): the identity is a server-composed
 * "{line1} - {city}" label.
 */
export interface LeadOperationalSiteRef {
  id: number
  label: string
}

/**
 * Single lead detail returned by GET/POST/PATCH /leads (envelope `data`).
 * Matches `LeadResource`. `referent_id`/`campaign_id` are always set (BR-1);
 * the other 4 fields are nullable.
 */
export interface LeadDetail {
  id: number
  referent_id: number
  referent: LeadRelationRef | null
  campaign_id: number
  campaign: LeadCampaignRef | null
  operational_site_id: number | null
  operational_site: LeadOperationalSiteRef | null
  source_id: number | null
  source: LeadRelationRef | null
  operator_id: number | null
  operator: LeadRelationRef | null
  /** BR-3/spec 0026: single boolean conversion flag, subject to the leads field-permission ceiling. */
  is_converted: boolean
  notes: string | null
  created_at: string
  updated_at: string
}

/**
 * A `LeadDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /leads/{id}`. Used to seed the
 * edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface LeadDetailWithPermissions extends LeadDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /leads (create). `referent_id`/`campaign_id` are required
 * (BR-1); the other 4 fields are optional/nullable.
 */
export interface CreateLeadPayload {
  referent_id: number
  campaign_id: number
  operational_site_id?: number | null
  source_id?: number | null
  operator_id?: number | null
  /** Optional; the backend defaults it to `false` on create (spec 0026). */
  is_converted?: boolean
  notes?: string | null
}

/** Payload for PATCH /leads/{id} (partial update). Every field is optional (sparse diff). */
export type UpdateLeadPayload = Partial<CreateLeadPayload>

/** Discriminated form mode shared by the form hook/meta-resolver and `LeadForm`. */
export type LeadFormMode = { type: 'create' } | { type: 'edit'; lead: LeadDetailWithPermissions }
