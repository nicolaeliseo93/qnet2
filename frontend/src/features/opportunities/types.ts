/**
 * Opportunities CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely opportunities-specific. Source of truth: spec 0040 frozen
 * `data_contract`. Only `name`/`registry_id` are required (D-4); every other
 * relation is nullable.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** A hydrated `{id, name}` relation projection, shared by every plain single-relation field. */
export interface OpportunityRelationRef {
  id: number
  name: string
}

/**
 * The linked operational site's identity, as exposed by
 * `OpportunityResource.operational_site`. `operational_sites` has no `name`
 * column: the identity is a server-composed label (mirrors leads).
 */
export interface OpportunityOperationalSiteRef {
  id: number
  label: string
}

/** The linked lead's identity, as exposed by `OpportunityResource.lead` (the lead's referent name, BR-1). */
export interface OpportunityLeadRef {
  id: number
  label: string
}

/** A manager ref carrying its static "G.A. n" `position` (1-based) on top of the person ref. */
export interface OpportunityManagerRef {
  id: number
  name: string
  position: number
}

/**
 * Single opportunity detail returned by GET/POST/PATCH /opportunities
 * (envelope `data`). Matches `OpportunityResource`.
 */
export interface OpportunityDetail {
  id: number
  name: string
  registry_id: number
  registry: OpportunityRelationRef | null
  /** Mandatory since spec 0040 amendment A-2 (was nullable in the original contract). */
  company_id: number
  company: OpportunityRelationRef | null
  /** Mandatory since spec 0040 amendment A-2 (was nullable in the original contract). */
  company_site_id: number
  company_site: OpportunityRelationRef | null
  /** Mandatory since spec 0040 amendment A-2; still derivable+lockable from a Lead that owns one (BR-1/BR-2). */
  operational_site_id: number
  operational_site: OpportunityOperationalSiteRef | null
  business_function_id: number | null
  business_function: OpportunityRelationRef | null
  referent_id: number | null
  referent: OpportunityRelationRef | null
  commercial_id: number | null
  commercial: OpportunityRelationRef | null
  reporter_id: number | null
  reporter: OpportunityRelationRef | null
  supervisor_id: number | null
  supervisor: OpportunityRelationRef | null
  source_id: number | null
  source: OpportunityRelationRef | null
  product_category_id: number | null
  product_category: OpportunityRelationRef | null
  lead_id: number | null
  lead: OpportunityLeadRef | null
  /** Filled manager ids as ordered "G.A. n" cards (name + position), mirrors registries. */
  managers: OpportunityManagerRef[]
  start_date: string | null
  /** Decimal(15,2), as `projects.total_budget`: the server may serialize it as a numeric string. */
  estimated_value: string | number | null
  expected_close_date: string | null
  success_probability: number | null
  /**
   * BR-2: keys of the fields whose value was derived from the linked Lead's
   * campaign and is therefore locked (immutable, even server-side). Empty
   * when `lead_id` is null.
   */
  locked_fields: string[]
  created_at: string
  updated_at: string
}

/**
 * An `OpportunityDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /opportunities/{id}`. Used to seed
 * the edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface OpportunityDetailWithPermissions extends OpportunityDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /opportunities (create). `name`/`registry_id` are required
 * (D-4); every other field is optional/nullable. `lead_id`, when set, derives
 * several fields server-side (BR-1) — the client must not also send a value
 * for a field whose derivation is non-null (422 `prohibited`).
 */
export interface CreateOpportunityPayload {
  name: string
  /**
   * Required for a manual create (D-4, enforced by the Zod schema); OMITTED
   * entirely (not merely repeated) when creating from a Lead and the value is
   * derived (BR-1/BR-2: sending it at all is `prohibited`, 422, even if it
   * matches). Optional here to allow that omission — `buildCreatePayload` is
   * the single place that decides whether to include it.
   */
  registry_id?: number
  /** Mandatory (A-2): never derivable from a Lead, always sent, never omitted. */
  company_id: number
  /** Mandatory (A-2): never derivable from a Lead, always sent, never omitted. */
  company_site_id: number
  /**
   * Mandatory (A-2), but still derivable+lockable from a Lead that owns one
   * (BR-1/BR-2) — optional here for the same reason as `registry_id`: OMITTED
   * entirely when locked, never merely repeated.
   */
  operational_site_id?: number
  business_function_id?: number | null
  referent_id?: number | null
  commercial_id?: number | null
  reporter_id?: number | null
  supervisor_id?: number | null
  source_id?: number | null
  product_category_id?: number | null
  lead_id?: number | null
  /** Ordered, gap-aware G.A. slots: index+1 = G.A. n, `null` = empty slot. */
  manager_slots?: (number | null)[]
  start_date?: string | null
  estimated_value?: number | null
  expected_close_date?: string | null
  success_probability?: number | null
}

/**
 * Payload for PATCH /opportunities/{id} (partial update). Every field is
 * optional (sparse diff). `lead_id` is immutable in update (BR-2, prohibited
 * server-side) and therefore not part of this shape at all.
 */
export type UpdateOpportunityPayload = Partial<Omit<CreateOpportunityPayload, 'lead_id'>>

/**
 * BR-1: the 6 fields a Lead's campaign can derive. `null` = the derivation
 * itself is null (e.g. the lead has no operational site) — the field then
 * stays free, per BR-2, and is NOT part of `locked_fields`.
 */
export interface OpportunityDefaultValues {
  referent_id: number | null
  source_id: number | null
  operational_site_id: number | null
  registry_id: number | null
  business_function_id: number | null
  product_category_id: number | null
}

/** Hydrated `{id, name|label}` projection of each `OpportunityDefaultValues` entry, for the picker's edit-mode-style hydration. */
export interface OpportunityDefaultReferences {
  referent: OpportunityRelationRef | null
  source: OpportunityRelationRef | null
  operational_site: OpportunityOperationalSiteRef | null
  registry: OpportunityRelationRef | null
  business_function: OpportunityRelationRef | null
  product_category: OpportunityRelationRef | null
}

/** Response of `GET /leads/{lead}/opportunity-defaults` (spec 0040 MT-6), already unwrapped from the envelope. */
export interface OpportunityDefaults {
  lead_id: number
  /** Non-null when the lead already has an opportunity (D-2: at most one per lead) — the create page then offers to go there instead. */
  existing_opportunity_id: number | null
  values: OpportunityDefaultValues
  references: OpportunityDefaultReferences
  /** Keys of `values` whose derivation is non-null (BR-2): locked in the form, `prohibited` if sent to the server. */
  locked_fields: string[]
}

/**
 * The create-from-lead context threaded through the form (spec 0040 MT-6):
 * resolved once, page-side, from `OpportunityDefaults`. `lockedFields` drives
 * both the UI (`forceDisabled`) and the payload (omitted entirely, BR-1/BR-2).
 */
export interface OpportunityFromLeadContext {
  leadId: number
  values: OpportunityDefaultValues
  references: OpportunityDefaultReferences
  lockedFields: string[]
}

/** Discriminated form mode shared by the form hook/meta-resolver and `OpportunityForm`. */
export type OpportunityFormMode =
  | { type: 'create'; fromLead?: OpportunityFromLeadContext }
  | { type: 'edit'; opportunity: OpportunityDetailWithPermissions }
