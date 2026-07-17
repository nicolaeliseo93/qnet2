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
 * A confirmed business-function + product-category pair (spec 0040 amendment
 * rev.3, AC-097/098/101): replaces the former single `business_function_id`/
 * `product_category_id` columns with a one-to-many collection of rows.
 */
export interface OpportunityProductLine {
  id: number
  business_function: OpportunityRelationRef
  product_category: OpportunityRelationRef
}

/** A product-line row as sent to the server (create/update payload, AC-099). */
export interface OpportunityProductLineInput {
  business_function_id: number
  product_category_id: number
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
  /** Amendment rev.3: replaces the former single `product_category`/`business_function` pair (AC-101). */
  product_lines: OpportunityProductLine[]
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
  referent_id?: number | null
  commercial_id?: number | null
  reporter_id?: number | null
  supervisor_id?: number | null
  source_id?: number | null
  lead_id?: number | null
  /** Ordered, gap-aware G.A. slots: index+1 = G.A. n, `null` = empty slot. */
  manager_slots?: (number | null)[]
  start_date?: string | null
  estimated_value?: number | null
  expected_close_date?: string | null
  success_probability?: number | null
  /**
   * Amendment rev.3 (AC-099): the server REPLACES the entire row collection
   * on every write. Always sent in full on create (even empty); the update
   * builder only includes it when the row SET actually changed.
   */
  product_lines: OpportunityProductLineInput[]
}

/**
 * Payload for PATCH /opportunities/{id} (partial update). Every field is
 * optional (sparse diff). `lead_id` is immutable in update (BR-2, prohibited
 * server-side) and therefore not part of this shape at all.
 */
export type UpdateOpportunityPayload = Partial<Omit<CreateOpportunityPayload, 'lead_id'>>

/**
 * BR-1: the fields a Lead's campaign can derive — only `source_id` and
 * `registry_id`. `null` = the derivation itself is null — the field then
 * stays free, per BR-2, and is NOT part of `locked_fields`. Amendment rev.3:
 * `business_function_id`/`product_category_id` are REMOVED from here — the
 * lead's derived function+category, when both exist, is exposed instead as
 * a `product_lines` row (see `OpportunityDefaults`), never locked.
 */
export interface OpportunityDefaultValues {
  referent_id: number | null
  source_id: number | null
  registry_id: number | null
}

/**
 * Hydrated `{id, name|label}` projection of each `OpportunityDefaultValues`
 * entry, for the picker's edit-mode-style hydration. No `referent` (spec 0041
 * D-3): the lead's identity is its anagrafica now, not its referent.
 */
export interface OpportunityDefaultReferences {
  source: OpportunityRelationRef | null
  registry: OpportunityRelationRef | null
}

/** Response of `GET /leads/{lead}/opportunity-defaults` (spec 0040 MT-6, amendment rev.3), already unwrapped from the envelope. */
export interface OpportunityDefaults {
  lead_id: number
  /** Non-null when the lead already has an opportunity (D-2: at most one per lead) — the create page then offers to go there instead. */
  existing_opportunity_id: number | null
  values: OpportunityDefaultValues
  references: OpportunityDefaultReferences
  /** Keys of `values` whose derivation is non-null (BR-2): locked in the form, `prohibited` if sent to the server. */
  locked_fields: string[]
  /**
   * AC-102/103: 0 or 1 seed row — present only when BOTH the lead/campaign's
   * effective business function AND product category exist. EDITABLE and
   * REMOVABLE in the form, never part of `locked_fields`.
   */
  product_lines: OpportunityProductLine[]
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
  /** AC-102/103: the lead's 0/1 seed row, editable/removable, never locked. */
  productLines: OpportunityProductLine[]
}

/** Discriminated form mode shared by the form hook/meta-resolver and `OpportunityForm`. */
export type OpportunityFormMode =
  | { type: 'create'; fromLead?: OpportunityFromLeadContext }
  | { type: 'edit'; opportunity: OpportunityDetailWithPermissions }
