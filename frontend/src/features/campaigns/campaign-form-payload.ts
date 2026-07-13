import type {
  CampaignDetail,
  CreateCampaignPayload,
  UpdateCampaignPayload,
} from '@/features/campaigns/types'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/** The 4 BR-2 classification fields, never sent when the campaign is linked to a project. */
const DERIVED_FIELDS = [
  'project_status_id',
  'business_function_id',
  'state_id',
  'product_category_id',
] as const

/**
 * Builds the create payload: generic fields + valued custom fields. `code` is
 * never sent (BR-1). BR-2: when `project_id` is set, the 4 classification
 * fields are omitted entirely — the backend derives/forces them from the
 * project and rejects an explicit value (AC-022) — regardless of what the
 * (disabled, read-only) form controls currently hold.
 */
export function buildCreatePayload(values: CampaignFormValues): CreateCampaignPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  const linked = values.project_id !== null

  return {
    name: values.name,
    project_id: values.project_id,
    description: values.description,
    registry_id: values.registry_id,
    source_id: values.source_id,
    partner_id: values.partner_id,
    ...(linked
      ? {}
      : {
          // Validated non-null by the schema's required-when-standalone superRefine before submit.
          project_status_id: values.project_status_id as number,
          business_function_id: values.business_function_id,
          state_id: values.state_id,
          product_category_id: values.product_category_id,
        }),
    start_date: values.start_date || null,
    end_date: values.end_date || null,
    total_budget: values.total_budget,
    target_lead: values.target_lead,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original campaign (spec 0023). `code` is never sent (BR-1). BR-2: the 4
 * classification fields are only ever included in the diff when the campaign
 * is (or remains) standalone — a transition to linked is carried entirely by
 * the changed `project_id`, and the backend zeroes the 4 fields server-side.
 */
export function buildUpdatePayload(
  values: CampaignFormValues,
  original: CampaignDetail,
): UpdateCampaignPayload {
  const payload: UpdateCampaignPayload = {}
  const linked = values.project_id !== null

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.project_id !== original.project_id) {
    payload.project_id = values.project_id
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.registry_id !== original.registry_id) {
    payload.registry_id = values.registry_id
  }
  if (values.source_id !== original.source_id) {
    payload.source_id = values.source_id
  }
  if (values.partner_id !== original.partner_id) {
    payload.partner_id = values.partner_id
  }
  if (!linked) {
    // A linked→standalone transition (BR-2): the campaign's own derived
    // columns were actually NULL in DB while `original` exposed the
    // project's EFFECTIVE values (read-through) — a diff against them would
    // be misleading, so every required field is sent as-is.
    const wasLinked = original.project_id !== null
    for (const field of DERIVED_FIELDS) {
      if (wasLinked || values[field] !== original[field]) {
        payload[field] = values[field]
      }
    }
  }
  const startDate = values.start_date || null
  if (startDate !== original.start_date) {
    payload.start_date = startDate
  }
  const endDate = values.end_date || null
  if (endDate !== original.end_date) {
    payload.end_date = endDate
  }
  const originalTotalBudget = original.total_budget === null ? null : Number(original.total_budget)
  if (values.total_budget !== originalTotalBudget) {
    payload.total_budget = values.total_budget
  }
  if (values.target_lead !== original.target_lead) {
    payload.target_lead = values.target_lead
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
