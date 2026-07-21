/**
 * Opportunity workflow configurator types (spec 0047, Lane C). Source of
 * truth: the frozen backend contract (`OpportunityWorkflowResource`,
 * `OpportunityWorkflowStatusResource`, `CriterionFieldRegistry`). A workflow
 * is a NEW, distinct dimension from `opportunity-statuses` (the sales
 * pipeline) — it is not modeled on that feature's types.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { StatusGroupValue } from '@/features/status-reorder/types'

/** Marks a workflow-status row as one of the two per-set pinned rows (`open`/`closed`), or `null` for a custom row. */
export type WorkflowStatusSystemKey = 'open' | 'closed' | null

/** One allow-listed criterion field, as returned by GET /opportunity-workflows/criterion-fields (AC-022). */
export interface CriterionFieldOption {
  field: string
  /** i18n key (e.g. "opportunityWorkflows.criterionFields.state_id"), not a display string. */
  label: string
  /** for-select resource segment used to pick this field's `value_id`. */
  for_select_resource: string
  multi_valued: boolean
}

/** One criterion of a workflow, hydrated with its resolved display label. */
export interface OpportunityWorkflowCriterion {
  id: number
  field: string
  value_id: number
  value_label: string
}

/** One status row of a workflow's (or the global default's) set. */
export interface OpportunityWorkflowStatusItem {
  id: number
  name: string
  color: string | null
  sort_order: number
  system_key: WorkflowStatusSystemKey
  group: StatusGroupValue
}

/** Full workflow detail, as returned by GET/POST/PUT/PATCH /opportunity-workflows(/{id}). */
export interface OpportunityWorkflowDetail {
  id: number
  name: string
  is_active: boolean
  criteria: OpportunityWorkflowCriterion[]
  statuses: OpportunityWorkflowStatusItem[]
  created_at: string
  updated_at: string
}

/** An `OpportunityWorkflowDetail` carrying the actor's authorization metadata for this instance (spec 0004). */
export interface OpportunityWorkflowDetailWithPermissions extends OpportunityWorkflowDetail {
  permissions: ResourcePermissions
}

/** One `criteria[]` entry accepted by POST/PUT/PATCH — `value_id` is always resolved before submit. */
export interface CreateOpportunityWorkflowCriterionPayload {
  field: string
  value_id: number
}

/** One `statuses[]` entry accepted by POST (create): custom, intermediate rows only — no `id`, the system rows are auto-created. */
export interface CreateOpportunityWorkflowStatusPayload {
  name: string
  color?: string | null
  group: StatusGroupValue
}

/**
 * One `statuses[]` entry accepted by PUT/PATCH (update) or the default-status
 * endpoint: `id` present = update an existing row (system or custom), absent
 * = a new custom row. Sort order is positional (array index), never sent
 * explicitly.
 */
export interface UpdateOpportunityWorkflowStatusPayload extends CreateOpportunityWorkflowStatusPayload {
  id?: number
}

/** Payload for POST /opportunity-workflows. */
export interface CreateOpportunityWorkflowPayload {
  name: string
  is_active?: boolean
  criteria: CreateOpportunityWorkflowCriterionPayload[]
  statuses?: CreateOpportunityWorkflowStatusPayload[]
}

/** Payload for PUT/PATCH /opportunity-workflows/{id} (sparse per-field; `criteria`/`statuses` are authoritative syncs when present). */
export interface UpdateOpportunityWorkflowPayload {
  name?: string
  is_active?: boolean
  criteria?: CreateOpportunityWorkflowCriterionPayload[]
  statuses?: UpdateOpportunityWorkflowStatusPayload[]
}

/** Payload for PUT /opportunity-workflows/default-statuses (the GLOBAL set; always a full `statuses` array). */
export interface UpdateDefaultStatusesPayload {
  statuses: UpdateOpportunityWorkflowStatusPayload[]
}

/** Discriminated form mode shared by the form hook/meta-resolver and `OpportunityWorkflowForm`. */
export type OpportunityWorkflowFormMode =
  | { type: 'create' }
  | { type: 'edit'; opportunityWorkflow: OpportunityWorkflowDetailWithPermissions }

/**
 * One status row as edited locally by `<WorkflowStatusesEditor>` (a
 * SortableList-driven local array, not an RHF field array — mirrors
 * `StatusReorderItem`/`useStatusReorder`). `id` is the STRING identity
 * `<SortableList>` requires (mirrors every other row's stringified id);
 * `statusId` is the persisted backend id, or `undefined` for a row not yet
 * created (a freshly-added custom row, or a placeholder pinned system row
 * shown before the workflow itself is created — AC-004: the backend
 * auto-creates the real open/closed rows on create, so a placeholder never
 * reaches the payload).
 */
export interface WorkflowStatusFormRow {
  id: string
  statusId?: number
  name: string
  color: string | null
  group: StatusGroupValue
  system_key: WorkflowStatusSystemKey
}
