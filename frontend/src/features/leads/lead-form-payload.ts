import { entriesToRecord } from '@/features/leads/extra-fields'
import type { CreateLeadPayload, LeadDetail, UpdateLeadPayload } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/**
 * Builds the create payload. `registry_id`/`campaign_id` are validated
 * non-null by the schema's refine before submit (BR-1, D-1); `lead_status_id`
 * is sent as-is (spec 0039 D-3: `null` falls back to the system "Nuovo"
 * status server-side).
 */
export function buildCreatePayload(values: LeadFormValues): CreateLeadPayload {
  return {
    registry_id: values.registry_id as number,
    campaign_id: values.campaign_id as number,
    lead_status_id: values.lead_status_id,
    operational_site_id: values.operational_site_id,
    source_id: values.source_id,
    operator_id: values.operator_id,
    notes: values.notes,
    extra_fields: entriesToRecord(values.extra_fields),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original lead (spec 0024, sparse diff, mirrors campaigns). `registry_id`/
 * `campaign_id`, when changed, are always non-null (the schema forbids
 * clearing either, BR-1, D-1). `lead_status_id` may be cleared to `null`
 * (spec 0039 D-3), the server then falls back to the system "Nuovo" status.
 */
export function buildUpdatePayload(values: LeadFormValues, original: LeadDetail): UpdateLeadPayload {
  const payload: UpdateLeadPayload = {}

  if (values.registry_id !== original.registry_id) {
    payload.registry_id = values.registry_id as number
  }
  if (values.campaign_id !== original.campaign_id) {
    payload.campaign_id = values.campaign_id as number
  }
  if (values.lead_status_id !== original.lead_status_id) {
    payload.lead_status_id = values.lead_status_id
  }
  if (values.operational_site_id !== original.operational_site_id) {
    payload.operational_site_id = values.operational_site_id
  }
  if (values.source_id !== original.source_id) {
    payload.source_id = values.source_id
  }
  if (values.operator_id !== original.operator_id) {
    payload.operator_id = values.operator_id
  }
  if (values.notes !== original.notes) {
    payload.notes = values.notes
  }
  const nextExtraFields = entriesToRecord(values.extra_fields)
  if (!extraFieldsEqual(nextExtraFields, original.extra_fields)) {
    payload.extra_fields = nextExtraFields
  }

  return payload
}

/** Order-independent equality of two `extra_fields` records, for the update sparse diff. */
function extraFieldsEqual(a: Record<string, string> | null, b: Record<string, string> | null): boolean {
  if (a === null || b === null) return a === b
  const aKeys = Object.keys(a).sort()
  const bKeys = Object.keys(b).sort()
  if (aKeys.length !== bKeys.length) return false
  return aKeys.every((key, index) => key === bKeys[index] && a[key] === b[key])
}
