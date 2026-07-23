import type { AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import type { ProductLineRow } from '@/features/product-lines/product-lines-field'
import type {
  CreateRequestPayload,
  CreateRequestProductLinePayload,
  RequestClientAddressPayload,
  RequestClientContactPayload,
  RequestClientIdentityPayload,
} from '@/features/request-management/types'

/** The wire shape of the client's identity (full create, no `id`: the server always makes a new card). */
function toClientIdentityPayload(draft: PersonalDataDraft): RequestClientIdentityPayload {
  return {
    type: draft.type,
    first_name: draft.first_name,
    last_name: draft.last_name,
    company_name: draft.company_name,
    tax_code: draft.tax_code,
    vat_number: draft.vat_number,
    sdi_code: draft.sdi_code,
    birth_date: draft.birth_date,
    // Mirrors the card form's own normalization: an individual always carries
    // a gender (default male), a company carries none.
    gender: draft.type === 'company' ? null : (draft.gender ?? 'male'),
  }
}

/** The wire row of one buffered client contact (no `id`: every one is new). */
function toClientContactPayload(draft: ContactDraft): RequestClientContactPayload {
  return { type: draft.type, value: draft.value, label: draft.label, is_primary: draft.is_primary }
}

/** The wire row of the single buffered client address (no `id`: it is new). */
function toClientAddressPayload(draft: AddressDraft): RequestClientAddressPayload {
  return {
    line1: draft.line1,
    line2: draft.line2,
    postal_code: draft.postal_code,
    city_id: draft.city_id,
    province_id: draft.province_id,
    state_id: draft.state_id,
    country_id: draft.country_id,
  }
}

/** Rows are guaranteed complete (both ids chosen) by `buildRequestCreateSchema` before this ever runs. */
function toProductLinesPayload(rows: ProductLineRow[]): CreateRequestProductLinePayload[] {
  return rows.map((row) => ({
    business_function_id: row.business_function_id as number,
    product_category_id: row.product_category_id as number,
  }))
}

export interface BuildRequestCreatePayloadArgs {
  registryId: number | null
  identity: PersonalDataDraft
  contacts: ContactDraft[]
  address: AddressDraft | null
  productLines: ProductLineRow[]
}

/**
 * Builds the frozen `POST /api/request-management` payload (spec 0057, D-2):
 * exactly one anagrafica source. `registryId` set — the client identity/
 * contacts/address buffers are dropped entirely, the server rejects both
 * branches sent together; unset — they are mapped onto the flat `client_*`
 * blocks the endpoint expects.
 */
export function buildRequestCreatePayload({
  registryId,
  identity,
  contacts,
  address,
  productLines,
}: BuildRequestCreatePayloadArgs): CreateRequestPayload {
  const product_lines = toProductLinesPayload(productLines)

  if (registryId !== null) {
    return { registry_id: registryId, product_lines }
  }

  return {
    client_identity: toClientIdentityPayload(identity),
    client_contacts: contacts.map(toClientContactPayload),
    ...(address ? { client_address: toClientAddressPayload(address) } : {}),
    product_lines,
  }
}
