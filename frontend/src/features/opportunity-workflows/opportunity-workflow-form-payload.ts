import type {
  CreateOpportunityWorkflowCriterionPayload,
  CreateOpportunityWorkflowPayload,
  CreateOpportunityWorkflowStatusPayload,
  UpdateDefaultStatusesPayload,
  UpdateOpportunityWorkflowPayload,
  UpdateOpportunityWorkflowStatusPayload,
  WorkflowStatusFormRow,
} from '@/features/opportunity-workflows/types'
import type { CreateOpportunityWorkflowFormValues } from '@/features/opportunity-workflows/opportunity-workflow-schema'

/** One `criteria[]` row exactly as the RHF field array edits it (each id individually nullable while the row fills in). */
export type CriterionFormRow = CreateOpportunityWorkflowFormValues['criteria'][number]

/**
 * Drops any row still missing a `field`/`value_id` and casts the rest to the
 * wire shape. Defensive only: the schema's `superRefine` already blocks
 * submit on an incomplete or duplicate-field row (mirrors
 * `opportunity-form-payload.ts`'s `completeProductLines`).
 */
function buildCriteriaPayload(rows: CriterionFormRow[]): CreateOpportunityWorkflowCriterionPayload[] {
  return rows
    .filter(
      (row): row is { field: string; value_id: number } =>
        row.field !== null && row.field !== '' && row.value_id !== null,
    )
    .map((row) => ({ field: row.field, value_id: row.value_id }))
}

/**
 * Builds the CREATE `statuses[]` payload: every row in visual order — the
 * custom (intermediate) rows plus the 2 pinned rows carrying their
 * `system_key`, so the backend seeds the auto-created open/closed rows with
 * the name/color the user typed (AC-004).
 */
function buildStatusesCreatePayload(rows: WorkflowStatusFormRow[]): CreateOpportunityWorkflowStatusPayload[] {
  return rows.map((row) => ({
    name: row.name,
    color: row.color,
    group: row.group,
    system_key: row.system_key,
  }))
}

/**
 * Builds the UPDATE/default-statuses `statuses[]` payload: every row that
 * has a real, persisted identity — `id` present = update (system or
 * custom), absent = a new custom row — in visual order (positional
 * `sort_order` for the customs, AC-025). A placeholder pinned row (no
 * `statusId` yet) never occurs in edit mode (every row hydrates from a
 * persisted `OpportunityWorkflowDetail`/default set), but is filtered out
 * defensively all the same.
 */
function buildStatusesUpdatePayload(rows: WorkflowStatusFormRow[]): UpdateOpportunityWorkflowStatusPayload[] {
  return rows
    .filter((row) => row.statusId !== undefined || row.system_key === null)
    .map((row) => ({
      id: row.statusId,
      name: row.name,
      color: row.color,
      group: row.group,
    }))
}

/** Builds the create payload: `name`, `is_active`, `criteria` (min:1) and the custom `statuses`. */
export function buildCreatePayload(
  values: CreateOpportunityWorkflowFormValues,
  statusRows: WorkflowStatusFormRow[],
): CreateOpportunityWorkflowPayload {
  return {
    name: values.name,
    is_active: values.is_active,
    criteria: buildCriteriaPayload(values.criteria),
    statuses: buildStatusesCreatePayload(statusRows),
  }
}

/**
 * Builds the update payload: always the FULL authoritative shape (`criteria`
 * is a full-replace sync per the frozen contract; `statuses` likewise syncs
 * every custom row + order) rather than a sparse diff — mirrors the
 * parent+children pattern used by `syncProductLines` (spec 0040), where the
 * nested collections are never diffed positionally.
 */
export function buildUpdatePayload(
  values: CreateOpportunityWorkflowFormValues,
  statusRows: WorkflowStatusFormRow[],
): UpdateOpportunityWorkflowPayload {
  return {
    name: values.name,
    is_active: values.is_active,
    criteria: buildCriteriaPayload(values.criteria),
    statuses: buildStatusesUpdatePayload(statusRows),
  }
}

/** Builds the PUT /opportunity-workflows/default-statuses payload from the shared editor's local rows. */
export function buildDefaultStatusesPayload(statusRows: WorkflowStatusFormRow[]): UpdateDefaultStatusesPayload {
  return { statuses: buildStatusesUpdatePayload(statusRows) }
}
