/**
 * Leads CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * leads-specific. Source of truth: spec 0024 frozen `data_contract`. A Lead
 * has exactly 6 fields, no `code` (D-3).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Hydrated `{id, name}` relation shared by registry/source/operator. */
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
 * The linked opportunity's minimal identity, as exposed by `LeadResource.opportunity`
 * (spec 0040 MT-6, D-2: at most one per lead). `null` when the lead has none yet.
 */
export interface LeadOpportunityRef {
  id: number
  name: string
}

/**
 * Single lead detail returned by GET/POST/PATCH /leads (envelope `data`).
 * Matches `LeadResource`. `registry_id`/`campaign_id` are always set (BR-1,
 * D-1; spec 0041 D-1: the contact is the anagrafica, not the referent); the
 * other fields are nullable. `lead_status` is derived from assignment and
 * opportunity state.
 *
 * `extra_fields` (spec 0033, AC-014) is a free-form key/value store: no
 * fixed shape, no per-field permissions. Keys either mirror an imported
 * file's original column name or are typed manually in the form.
 *
 * `opportunity` (spec 0040 MT-6) is optional here — not because the backend
 * ever omits it (it always does), but so every pre-existing `LeadDetail`
 * fixture across this feature's test suites keeps type-checking unchanged;
 * treat a missing key the same as `null` (no linked opportunity).
 */
export interface LeadDetail {
  id: number
  registry_id: number
  registry: LeadRelationRef | null
  campaign_id: number
  campaign: LeadCampaignRef | null
  lead_status: LeadLifecycleStatus
  operational_site_id: number | null
  operational_site: LeadOperationalSiteRef | null
  source_id: number | null
  source: LeadRelationRef | null
  /**
   * Spec 0047 (D1, AC-001): the Regione, DERIVED server-side from the
   * operational site's primary address — never a client input, no picker.
   * Optional (like `opportunity` below) so every pre-existing `LeadDetail`
   * fixture keeps type-checking unchanged; a missing key means `null`.
   */
  state_id?: number | null
  state?: LeadRelationRef | null
  operator_id: number | null
  operator: LeadRelationRef | null
  notes: string | null
  extra_fields: Record<string, string> | null
  opportunity?: LeadOpportunityRef | null
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
 * Payload for POST /leads (create). `registry_id`/`campaign_id` are required
 * (BR-1, D-1); the other fields are optional/nullable. Lead status is derived
 * server-side and is not part of the write contract.
 */
export interface CreateLeadPayload {
  registry_id: number
  campaign_id: number
  operational_site_id?: number | null
  source_id?: number | null
  operator_id?: number | null
  notes?: string | null
  extra_fields?: Record<string, string> | null
  /**
   * Spec 0044: requests atomic conversion into a linked Opportunity when
   * true (default false). Create-only — `UpdateLeadPayload` never carries
   * it, edit-mode conversion is out of scope.
   */
  convert_to_opportunity?: boolean
}

/** Payload for PATCH /leads/{id} (partial update). Every field is optional (sparse diff). */
export type UpdateLeadPayload = Partial<CreateLeadPayload>

/** Discriminated form mode shared by the form hook/meta-resolver and `LeadForm`. */
export type LeadFormMode = { type: 'create' } | { type: 'edit'; lead: LeadDetailWithPermissions }

export type LeadLifecycleStatus = 'not_associated' | 'associated' | 'converted_to_opportunity'
