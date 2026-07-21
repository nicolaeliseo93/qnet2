import type {
  CreateProjectPayload,
  ProjectDetail,
  UpdateProjectPayload,
} from '@/features/projects/types'
import type { ProjectFormValues } from '@/features/projects/use-project-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/**
 * Builds the create payload: generic fields + valued custom fields. `code` is
 * included only when set (trimmed, non-empty) — an empty/absent value falls
 * back to server-side sequential generation (spec 0025 AC-010).
 */
export function buildCreatePayload(values: ProjectFormValues): CreateProjectPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  const code = values.code.trim()
  return {
    ...(code ? { code } : {}),
    name: values.name,
    // Nullable/optional (spec 0039 D-3): the server falls back to the system "Nuovo" status when omitted.
    pipeline_status_id: values.pipeline_status_id,
    description: values.description,
    business_function_id: values.business_function_id,
    country_id: values.country_id,
    state_id: values.state_id,
    province_id: values.province_id,
    city_id: values.city_id,
    product_category_id: values.product_category_id,
    partner_id: values.partner_id,
    operational_site_id: values.operational_site_id,
    start_date: values.start_date || null,
    end_date: values.end_date || null,
    total_budget: values.total_budget,
    target_lead: values.target_lead,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original project (spec 0023). `code` is never sent: it is immutable after
 * create (spec 0025 AC-011).
 */
export function buildUpdatePayload(
  values: ProjectFormValues,
  original: ProjectDetail,
): UpdateProjectPayload {
  const payload: UpdateProjectPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.pipeline_status_id !== original.pipeline_status_id) {
    payload.pipeline_status_id = values.pipeline_status_id
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.business_function_id !== original.business_function_id) {
    payload.business_function_id = values.business_function_id
  }
  if (values.country_id !== original.country_id) {
    payload.country_id = values.country_id
  }
  if (values.state_id !== original.state_id) {
    payload.state_id = values.state_id
  }
  if (values.province_id !== original.province_id) {
    payload.province_id = values.province_id
  }
  if (values.city_id !== original.city_id) {
    payload.city_id = values.city_id
  }
  if (values.product_category_id !== original.product_category_id) {
    payload.product_category_id = values.product_category_id
  }
  if (values.partner_id !== original.partner_id) {
    payload.partner_id = values.partner_id
  }
  if (values.operational_site_id !== original.operational_site_id) {
    payload.operational_site_id = values.operational_site_id
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
