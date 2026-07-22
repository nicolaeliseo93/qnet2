import { isEqualCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { AddressDraft, ContactDraft } from '@/features/personal-data/types'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type {
  RequestClientAddressPayload,
  RequestClientContactPayload,
  RequestWorkPanel,
  UpdateRequestWorkPayload,
} from '@/features/request-management/types'

/**
 * True when at least one applicable attribute's current value differs from
 * the panel's loaded one. `attribute_values` is a merge/replace-whole-map
 * field server-side (spec 0049 data_contract): there is no per-code sparse
 * diff to compute, only whether the map as a whole needs resending.
 */
function attributeValuesChanged(
  current: Record<string, CustomFieldValue>,
  original: Record<string, unknown>,
  codes: string[],
): boolean {
  return codes.some(
    (code) => !isEqualCustomFieldValue(current[code] ?? null, (original[code] as CustomFieldValue) ?? null),
  )
}

/**
 * The mappers below take the STRUCTURAL minimum both sides share — the
 * buffered draft (`ContactDraft`/`AddressDraft`) and the panel's loaded
 * projection (`RequestContact`/`Address`) — so current and original map
 * through the exact same function and stay comparable.
 */
type ClientContactSource = Pick<ContactDraft, 'type' | 'value' | 'label' | 'is_primary'> & { id?: number }

type ClientAddressSource = Pick<
  AddressDraft,
  'line1' | 'line2' | 'postal_code' | 'city_id' | 'province_id' | 'state_id' | 'country_id'
> & { id?: number }

/** The wire row of one buffered client contact (`id` present = update). */
function toClientContactPayload(draft: ClientContactSource): RequestClientContactPayload {
  return {
    ...(draft.id !== undefined ? { id: draft.id } : {}),
    type: draft.type,
    value: draft.value,
    label: draft.label,
    is_primary: draft.is_primary,
  }
}

/** The wire row of the single buffered client address (`id` present = update). */
function toClientAddressPayload(draft: ClientAddressSource): RequestClientAddressPayload {
  return {
    ...(draft.id !== undefined ? { id: draft.id } : {}),
    line1: draft.line1,
    line2: draft.line2,
    postal_code: draft.postal_code,
    city_id: draft.city_id,
    province_id: draft.province_id,
    state_id: draft.state_id,
    country_id: draft.country_id,
  }
}

/**
 * True when the buffered client block differs from what the panel loaded.
 * Compared on the WIRE shape, so a purely client-side difference (the `_key`
 * of a re-seeded draft) never counts as a change.
 */
function clientBlockChanged(current: unknown, original: unknown): boolean {
  return JSON.stringify(current) !== JSON.stringify(original)
}

/**
 * Builds the sparse PATCH payload (AC-062): only `opportunity_workflow_status_id`
 * and/or `attribute_values` are included, each only when it actually changed
 * from the loaded `panel`.
 */
export function buildRequestWorkPayload(
  values: RequestWorkFormValues,
  panel: RequestWorkPanel,
): UpdateRequestWorkPayload {
  const payload: UpdateRequestWorkPayload = {}

  const originalWorkflowStatusId = panel.workflow_status?.id ?? null
  if (values.opportunity_workflow_status_id !== originalWorkflowStatusId) {
    payload.opportunity_workflow_status_id = values.opportunity_workflow_status_id
  }

  if (values.next_callback_at !== (panel.next_callback_at ?? null)) {
    payload.next_callback_at = values.next_callback_at
  }

  const codes = panel.applicable_attributes.map((attribute) => attribute.code)
  if (attributeValuesChanged(values.attribute_values, panel.attribute_values, codes)) {
    payload.attribute_values = values.attribute_values
  }

  const contacts = values.client_contacts.map(toClientContactPayload)
  if (clientBlockChanged(contacts, panel.client_contacts.items.map(toClientContactPayload))) {
    payload.client_contacts = contacts
  }

  // Sent only when a row exists: clearing every field of the inline address
  // leaves the persisted one untouched — this panel has no delete affordance
  // for it, and the write path never deletes an address.
  const address = values.client_address[0]
  if (address) {
    const wire = toClientAddressPayload(address)
    const original = panel.client_address ? toClientAddressPayload(panel.client_address) : null
    if (clientBlockChanged(wire, original)) {
      payload.client_address = wire
    }
  }

  return payload
}
