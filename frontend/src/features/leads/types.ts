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
 * The linked lead status's identity, as exposed by `LeadResource.lead_status`
 * (spec 0029). Unlike the other relations, this one is NEVER null (D-1: the
 * FK is NOT NULL, every lead always has a status).
 */
export interface LeadStatusRef {
  id: number
  name: string
  color: string | null
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
 * Matches `LeadResource`. `referent_id`/`campaign_id`/`lead_status_id` are
 * always set (BR-1, D-1); the other 3 fields are nullable.
 *
 * `extra_fields` (spec 0033, AC-014) is a free-form key/value store: no
 * fixed shape, no per-field permissions. Keys either mirror an imported
 * file's original column name or are typed manually in the form.
 */
export interface LeadDetail {
  id: number
  referent_id: number
  referent: LeadRelationRef | null
  campaign_id: number
  campaign: LeadCampaignRef | null
  lead_status_id: number
  lead_status: LeadStatusRef
  operational_site_id: number | null
  operational_site: LeadOperationalSiteRef | null
  source_id: number | null
  source: LeadRelationRef | null
  operator_id: number | null
  operator: LeadRelationRef | null
  notes: string | null
  extra_fields: Record<string, string> | null
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
 * (BR-1, D-1); `lead_status_id` is nullable/optional (spec 0039 D-3: the
 * server falls back to the system "Nuovo" status when omitted); the other 3
 * fields are optional/nullable.
 */
export interface CreateLeadPayload {
  referent_id: number
  campaign_id: number
  lead_status_id?: number | null
  operational_site_id?: number | null
  source_id?: number | null
  operator_id?: number | null
  notes?: string | null
  extra_fields?: Record<string, string> | null
}

/** Payload for PATCH /leads/{id} (partial update). Every field is optional (sparse diff). */
export type UpdateLeadPayload = Partial<CreateLeadPayload>

/** Discriminated form mode shared by the form hook/meta-resolver and `LeadForm`. */
export type LeadFormMode = { type: 'create' } | { type: 'edit'; lead: LeadDetailWithPermissions }
