/**
 * Request-management ("Gestione Richieste") work-panel types. NOT a new
 * entity: the record IS an Opportunity, exposed through a dedicated
 * operational endpoint (spec 0049 frozen `data_contract`). Mirrors
 * `RequestManagementResource` 1:1 — do not add fields the backend doesn't
 * send.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { OwnerRef } from '@/features/personal-data/types'

/** Table/stats domain key of this module, shared by the table adapter. */
export const REQUEST_MANAGEMENT_DOMAIN = 'request-management'

/** A hydrated `{id, name}` relation projection (registry/referent/commercial). */
export interface RequestRelationRef {
  id: number
  name: string
}

/** The linked opportunity status's identity, read-only in this module. */
export interface RequestOpportunityStatusRef {
  id: number
  name: string
  color: string | null
}

/**
 * A resolved working-state row (spec 0047), identical shape to
 * `OpportunityWorkflowStatusRef` minus `group` (not part of this contract).
 */
export interface RequestWorkflowStatusRef {
  id: number
  name: string
  /** Free-text explanation of the status, shown under the option in the working-status select. */
  description: string | null
  color: string | null
  system_key: string | null
  /** Marks the status as one requiring an explanatory note (configuration only). */
  requires_note: boolean
}

/** A business-function + product-category pair of one of the opportunity's rows. */
export interface RequestProductLine {
  id: number
  business_function: RequestRelationRef
  product_category: RequestRelationRef
}

/** A single contact channel (ContactResource), as exposed to this module. */
export interface RequestContact {
  id: number
  type: string
  label: string | null
  value: string
  is_primary: boolean
}

/**
 * The PersonalData CARD ref backing a contacts block, already resolved by the
 * backend (Registry/Referent are not a valid `personable_type` for a
 * standalone card fetch) — fed straight into `ContactsManager`'s
 * `persistence` prop. `null` when the opportunity has no linked owner of
 * this kind yet (no card to persist against).
 */
export type RequestContactsOwnerRef = OwnerRef & { type: 'personal_data' }

/** A contacts block: the owner to persist against, plus its current contacts. */
export interface RequestContactsBlock {
  owner: RequestContactsOwnerRef | null
  items: RequestContact[]
}

/** A single selectable option of an enum/relation-typed attribute. */
export interface ApplicableAttributeOption {
  value: string
  label: string
  color: string | null
}

/**
 * A dynamic field derived from the Product Category's effective Attributes
 * (union, dedup by `code`, across all of the opportunity's product lines).
 */
export interface ApplicableAttribute {
  id: number
  code: string
  name: string
  type: string
  description: string | null
  help_text: string | null
  placeholder: string | null
  icon: string | null
  config: Record<string, unknown> | null
  relation_target: Record<string, unknown> | null
  is_required: boolean
  sort_order: number
  options: ApplicableAttributeOption[]
}

/** Read-only commercial context surfaced alongside the work panel. */
export interface RequestWorkContext {
  estimated_value: string | number | null
  expected_close_date: string | null
  success_probability: number | null
}

/**
 * The work panel returned by `GET /api/request-management/{opportunity}`
 * (envelope `data`). Matches `RequestManagementResource`.
 */
export interface RequestWorkPanel {
  id: number
  name: string
  registry: RequestRelationRef | null
  referent: RequestRelationRef | null
  commercial: RequestRelationRef | null
  /** Sales-pipeline status: read-only in this module. */
  opportunity_status: RequestOpportunityStatusRef | null
  workflow_status: RequestWorkflowStatusRef | null
  /** The resolved set the workflow-status select is limited to. */
  workflow_statuses: RequestWorkflowStatusRef[]
  product_lines: RequestProductLine[]
  client_contacts: RequestContactsBlock
  referent_contacts: RequestContactsBlock
  /** Union, dedup by `code`, of the effective attributes of every product line. */
  applicable_attributes: ApplicableAttribute[]
  /** Current values keyed by attribute `code`; `{}` when none. */
  attribute_values: Record<string, unknown>
  context: RequestWorkContext
}

/**
 * A `RequestWorkPanel` carrying the actor's authorization metadata for this
 * instance, as returned by both GET and PATCH `/api/request-management/{id}`.
 */
export interface RequestWorkPanelWithPermissions extends RequestWorkPanel {
  permissions: ResourcePermissions
}

/**
 * Payload for `PATCH /api/request-management/{opportunity}` (sparse diff):
 * only the sent keys change. `attribute_values`, when sent, replaces the
 * entire map (keys not included are left untouched server-side).
 */
export interface UpdateRequestWorkPayload {
  opportunity_workflow_status_id?: number | null
  attribute_values?: Record<string, unknown>
}
