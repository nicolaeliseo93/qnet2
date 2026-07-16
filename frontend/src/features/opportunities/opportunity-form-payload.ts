import type {
  CreateOpportunityPayload,
  OpportunityDetail,
  OpportunityManagerRef,
  UpdateOpportunityPayload,
} from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/** Create-from-lead context needed by the payload builder (spec 0040 MT-6): only what governs what NOT to send. */
export interface CreatePayloadFromLead {
  leadId: number
  /** BR-1/BR-2: keys whose value is server-derived — sending them at all is `prohibited` (422), even if it matches. */
  lockedFields: readonly string[]
}

/**
 * Builds the create payload. `name`, `registry_id`, `company_id`,
 * `company_site_id` and `operational_site_id` are validated non-null by the
 * schema's refine before submit (D-4/A-2) — `company_id`/`company_site_id`
 * are NEVER derivable from a Lead (amendment A-2) so they are always sent.
 * `registry_id`/`operational_site_id` are the exception: sent as-is UNLESS
 * locked (`fromLead`, BR-1) — either way the RHF value is always present at
 * submit time, just not always sent. The other 4 BR-1-derivable fields
 * (referent/source/business_function/product_category) are nullable either
 * way and sent as-is when not locked. When creating from a Lead (spec 0040
 * MT-6/A-1), every field named in `fromLead.lockedFields` is OMITTED
 * entirely (not merely repeated) and `lead_id` is appended.
 */
export function buildCreatePayload(
  values: OpportunityFormValues,
  fromLead?: CreatePayloadFromLead,
): CreateOpportunityPayload {
  const locked = new Set(fromLead?.lockedFields ?? [])

  const payload: CreateOpportunityPayload = {
    name: values.name.trim(),
    company_id: values.company_id as number,
    company_site_id: values.company_site_id as number,
    commercial_id: values.commercial_id,
    reporter_id: values.reporter_id,
    supervisor_id: values.supervisor_id,
    manager_slots: values.manager_slots,
    start_date: values.start_date,
    expected_close_date: values.expected_close_date,
    estimated_value: values.estimated_value,
    success_probability: values.success_probability,
  }

  if (!locked.has('registry_id')) {
    payload.registry_id = values.registry_id as number
  }
  if (!locked.has('referent_id')) {
    payload.referent_id = values.referent_id
  }
  if (!locked.has('source_id')) {
    payload.source_id = values.source_id
  }
  if (!locked.has('operational_site_id')) {
    payload.operational_site_id = values.operational_site_id as number
  }
  if (!locked.has('business_function_id')) {
    payload.business_function_id = values.business_function_id
  }
  if (!locked.has('product_category_id')) {
    payload.product_category_id = values.product_category_id
  }

  if (fromLead) {
    payload.lead_id = fromLead.leadId
  }

  return payload
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original opportunity (sparse diff, mirrors leads/registries). `lead_id` is
 * never part of the update shape (BR-2: immutable, `prohibited` server-side).
 * A locked field (BR-2) sent unchanged is a no-op for the server, so it is
 * simply omitted here like any other untouched field — the caller
 * (`use-opportunity-form`) is responsible for never letting a locked field's
 * RHF value drift from its derived original in the first place.
 */
export function buildUpdatePayload(
  values: OpportunityFormValues,
  original: OpportunityDetail,
): UpdateOpportunityPayload {
  const payload: UpdateOpportunityPayload = {}

  const trimmedName = values.name.trim()
  if (trimmedName !== original.name) {
    payload.name = trimmedName
  }
  if (values.registry_id !== original.registry_id) {
    payload.registry_id = values.registry_id as number
  }
  if (values.company_id !== original.company_id) {
    payload.company_id = values.company_id as number
  }
  if (values.company_site_id !== original.company_site_id) {
    payload.company_site_id = values.company_site_id as number
  }
  if (values.operational_site_id !== original.operational_site_id) {
    payload.operational_site_id = values.operational_site_id as number
  }
  if (values.business_function_id !== original.business_function_id) {
    payload.business_function_id = values.business_function_id
  }
  if (values.referent_id !== original.referent_id) {
    payload.referent_id = values.referent_id
  }
  if (values.commercial_id !== original.commercial_id) {
    payload.commercial_id = values.commercial_id
  }
  if (values.reporter_id !== original.reporter_id) {
    payload.reporter_id = values.reporter_id
  }
  if (values.supervisor_id !== original.supervisor_id) {
    payload.supervisor_id = values.supervisor_id
  }
  if (values.source_id !== original.source_id) {
    payload.source_id = values.source_id
  }
  if (values.product_category_id !== original.product_category_id) {
    payload.product_category_id = values.product_category_id
  }
  // Manager slots are ORDER- and GAP-sensitive (a slot's G.A. position is
  // meaningful), so compare positionally, not as an unordered set.
  if (!sameSlots(values.manager_slots, managerSlotsFromRefs(original.managers))) {
    payload.manager_slots = values.manager_slots
  }
  if (values.start_date !== original.start_date) {
    payload.start_date = values.start_date
  }
  if (values.expected_close_date !== original.expected_close_date) {
    payload.expected_close_date = values.expected_close_date
  }
  if (values.estimated_value !== normalizeDecimal(original.estimated_value)) {
    payload.estimated_value = values.estimated_value
  }
  // A-6: the form always holds a number (default 0); a null original hydrates
  // as 0, so compare against `?? 0` to avoid a spurious 0-write on an untouched
  // field ("0%" ≡ "not set").
  if (values.success_probability !== (original.success_probability ?? 0)) {
    payload.success_probability = values.success_probability
  }

  return payload
}

/**
 * `OpportunityDetail.managers` (spec 0040) carries hydrated `{id, name,
 * position}` refs, not the gap-aware slot array the form edits — unlike
 * registries, there is no dedicated `manager_slots` field on the detail
 * response. Rebuilds the positional, gap-aware slot array from the sparse
 * `managers` list so it can be compared against the form's current slots.
 */
export function managerSlotsFromRefs(managers: OpportunityManagerRef[]): (number | null)[] {
  const highestPosition = managers.reduce((max, manager) => Math.max(max, manager.position), 0)
  const slots: (number | null)[] = new Array(highestPosition).fill(null)
  managers.forEach((manager) => {
    slots[manager.position - 1] = manager.id
  })
  return slots
}

/** Positional (order- and gap-sensitive) comparison of two G.A. slot arrays. */
function sameSlots(a: (number | null)[], b: (number | null)[]): boolean {
  const length = Math.max(a.length, b.length)
  for (let index = 0; index < length; index += 1) {
    if ((a[index] ?? null) !== (b[index] ?? null)) {
      return false
    }
  }
  return true
}

/** Normalizes `estimated_value` (may be a decimal string from the backend) to a comparable number. */
export function normalizeDecimal(value: string | number | null): number | null {
  if (value === null) {
    return null
  }
  return typeof value === 'number' ? value : Number(value)
}
