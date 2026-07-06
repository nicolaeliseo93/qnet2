/**
 * Helpers for the buffered (controlled) personal-data editing flow: stable
 * client keys, seeding drafts from a persisted card, and mapping drafts back to
 * the nested wire shape submitted inside the user create/edit request
 * (ADR 0012 / spec 0003). Keeping this mapping here (not in the components or the
 * user form) keeps each consumer thin and the contract in one place.
 */

import type {
  Address,
  AddressDraft,
  Contact,
  ContactDraft,
  PersonalDataCard,
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
  PersonalDataType,
} from '@/features/personal-data/types'

let keyCounter = 0

/**
 * A blank personal-data draft, used to keep the card always active in the user
 * form (create and edit): the section is never empty, so there is no add/remove
 * affordance. Defaults to the `individual` type.
 */
export function emptyPersonalDataDraft(
  type: PersonalDataType = 'individual',
): PersonalDataDraft {
  return {
    type,
    title: null,
    first_name: null,
    last_name: null,
    company_name: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    contacts: [],
    addresses: [],
  }
}

/** A process-stable, collision-free key for a brand new draft row. */
export function nextDraftKey(): string {
  keyCounter += 1
  return `draft-${keyCounter}`
}

/* -------------------------------------------------------------------------- */
/* Seed: persisted card → draft tree (edit mode)                               */
/* -------------------------------------------------------------------------- */

function contactToDraft(contact: Contact): ContactDraft {
  return {
    _key: `contact-${contact.id}`,
    id: contact.id,
    type: contact.type,
    value: contact.value,
    label: contact.label,
    is_primary: contact.is_primary,
  }
}

function addressToDraft(address: Address): AddressDraft {
  return {
    _key: `address-${address.id}`,
    id: address.id,
    line1: address.line1,
    line2: address.line2,
    postal_code: address.postal_code,
    city_id: address.city_id,
    province_id: address.province_id,
    state_id: address.state_id,
    country_id: address.country_id,
    is_primary: address.is_primary,
  }
}

/** Maps a loaded card (with its children) to the buffered draft tree. */
export function cardToDraft(card: PersonalDataCard): PersonalDataDraft {
  return {
    id: card.id,
    type: card.type,
    title: card.title,
    first_name: card.first_name,
    last_name: card.last_name,
    company_name: card.company_name,
    tax_code: card.tax_code,
    vat_number: card.vat_number,
    sdi_code: card.sdi_code,
    birth_date: card.birth_date ? card.birth_date.slice(0, 10) : null,
    contacts: card.contacts.map(contactToDraft),
    addresses: card.addresses.map(addressToDraft),
  }
}

/* -------------------------------------------------------------------------- */
/* Submit: draft tree → nested wire payload (create + edit)                    */
/* -------------------------------------------------------------------------- */

/** A single contact in the nested `personal_data.contacts[]` wire shape. */
export interface PersonalDataContactPayload {
  id?: number
  type: string
  value: string
  label: string | null
  is_primary: boolean
}

/** A single address in the nested `personal_data.addresses[]` wire shape. */
export interface PersonalDataAddressPayload {
  id?: number
  line1: string
  line2: string | null
  postal_code: string | null
  city_id: number | null
  province_id: number | null
  state_id: number | null
  country_id: number | null
  is_primary: boolean
}

/**
 * The nested `personal_data` object accepted by the user write endpoints. Every
 * key is optional so a gated payload (spec 0008, `omitNonEditableFields`) can
 * drop the scalar fields/sections a resolver marks non-editable; `draftToPayload`
 * itself always returns every key (full replace, unaffected by this widening).
 */
export interface PersonalDataPayload {
  type?: PersonalDataDraft['type']
  title?: string | null
  first_name?: string | null
  last_name?: string | null
  company_name?: string | null
  tax_code?: string | null
  vat_number?: string | null
  sdi_code?: string | null
  birth_date?: string | null
  contacts?: PersonalDataContactPayload[]
  addresses?: PersonalDataAddressPayload[]
}

function contactToPayload(draft: ContactDraft): PersonalDataContactPayload {
  return {
    ...(draft.id !== undefined ? { id: draft.id } : {}),
    type: draft.type,
    value: draft.value,
    label: draft.label,
    is_primary: draft.is_primary,
  }
}

function addressToPayload(draft: AddressDraft): PersonalDataAddressPayload {
  return {
    ...(draft.id !== undefined ? { id: draft.id } : {}),
    line1: draft.line1,
    line2: draft.line2,
    postal_code: draft.postal_code,
    city_id: draft.city_id,
    province_id: draft.province_id,
    state_id: draft.state_id,
    country_id: draft.country_id,
    is_primary: draft.is_primary,
  }
}

/**
 * Maps the buffered draft tree to the nested `personal_data` wire payload. The
 * `contacts`/`addresses` arrays are always included (authoritative sync: an empty
 * array deletes all owned children).
 */
export function draftToPayload(draft: PersonalDataDraft): PersonalDataPayload {
  return {
    type: draft.type,
    title: draft.title,
    first_name: draft.first_name,
    last_name: draft.last_name,
    company_name: draft.company_name,
    tax_code: draft.tax_code,
    vat_number: draft.vat_number,
    sdi_code: draft.sdi_code,
    birth_date: draft.birth_date,
    contacts: draft.contacts.map(contactToPayload),
    addresses: draft.addresses.map(addressToPayload),
  }
}

/** The mapped payload's scalar keys, each gated by its own `personal_data.*` key. */
const SCALAR_PAYLOAD_KEYS = [
  'type',
  'title',
  'first_name',
  'last_name',
  'company_name',
  'tax_code',
  'vat_number',
  'sdi_code',
  'birth_date',
] as const

/**
 * Strips the scalar fields and child sections a resolver marks non-editable
 * from a mapped `personal_data` payload (spec 0008, defense in depth: the
 * backend enforces the same rule with a CHANGE-based guard). Without a
 * resolver, the payload is returned unchanged — the ungated behaviour required
 * for the self-service profile form (AC-013).
 */
export function omitNonEditableFields(
  payload: PersonalDataPayload,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): PersonalDataPayload {
  if (!fieldPermission) {
    return payload
  }

  const gated: PersonalDataPayload = { ...payload }
  for (const key of SCALAR_PAYLOAD_KEYS) {
    if (!fieldPermission(`personal_data.${key}`).editable) {
      delete gated[key]
    }
  }
  if (!fieldPermission('personal_data.contacts').editable) {
    delete gated.contacts
  }
  if (!fieldPermission('personal_data.addresses').editable) {
    delete gated.addresses
  }
  return gated
}
