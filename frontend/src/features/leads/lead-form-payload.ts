import { entriesToRecord } from '@/features/leads/extra-fields'
import type { CreateLeadPayload, LeadDetail, UpdateLeadPayload } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/**
 * Builds the create payload. `referent_id`/`campaign_id`/`lead_status_id`
 * are validated non-null by the schema's refine before submit (BR-1, D-1).
 */
export function buildCreatePayload(values: LeadFormValues): CreateLeadPayload {
  return {
    referent_id: values.referent_id as number,
    campaign_id: values.campaign_id as number,
    lead_status_id: values.lead_status_id as number,
    operational_site_id: values.operational_site_id,
    source_id: values.source_id,
    operator_id: values.operator_id,
    notes: values.notes,
    extra_fields: entriesToRecord(values.extra_fields),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original lead (spec 0024, sparse diff, mirrors campaigns). `referent_id`/
 * `campaign_id`/`lead_status_id`, when changed, are always non-null (the
 * schema forbids clearing any of them, BR-1, D-1).
 */
export function buildUpdatePayload(values: LeadFormValues, original: LeadDetail): UpdateLeadPayload {
  const payload: UpdateLeadPayload = {}

  if (values.referent_id !== original.referent_id) {
    payload.referent_id = values.referent_id as number
  }
  if (values.campaign_id !== original.campaign_id) {
    payload.campaign_id = values.campaign_id as number
  }
  if (values.lead_status_id !== original.lead_status_id) {
    payload.lead_status_id = values.lead_status_id as number
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
