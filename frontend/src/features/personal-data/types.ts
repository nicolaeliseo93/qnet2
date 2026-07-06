/**
 * Reusable, owner-agnostic personal-data module types. Mirror the backend API
 * resources 1:1 (PersonalDataResource / ContactResource / AddressResource) and
 * the polymorphic owner contract (ADR 0006).
 *
 * Everything here is keyed off a generic `OwnerRef` (a stable alias + id), so
 * the same components/hooks attach a card, contacts and addresses to ANY owner
 * type (user today; supplier/company tomorrow) with no per-owner duplication.
 */

/** A polymorphic owner: the public alias + id, exactly as the API expects. */
export interface OwnerRef {
  /** Stable alias from the backend allowlist (e.g. 'user', 'personal_data'). */
  type: string
  id: number
}

export type PersonalDataType = 'individual' | 'company'

/** Biological sex of a natural-person card (GenderEnum). Null for a company. */
export type Gender = 'male' | 'female'

/** A single contact channel (ContactResource). */
export interface Contact {
  id: number
  type: string
  label: string | null
  value: string
  is_primary: boolean
  contactable_type: string
  contactable_id: number
  created_at: string | null
}

/** A single address (AddressResource). */
export interface Address {
  id: number
  line1: string
  line2: string | null
  postal_code: string | null
  city_id: number | null
  province_id: number | null
  state_id: number | null
  country_id: number | null
  latitude: string | null
  longitude: string | null
  is_primary: boolean
  addressable_type: string
  addressable_id: number
  created_at: string | null
}

/** A personal-data card (PersonalDataResource) with its owned relations. */
export interface PersonalDataCard {
  id: number
  type: PersonalDataType
  first_name: string | null
  last_name: string | null
  company_name: string | null
  full_name: string
  ceo: string | null
  tax_code: string | null
  vat_number: string | null
  sdi_code: string | null
  birth_date: string | null
  gender: Gender | null
  personable_type: string
  personable_id: number
  contacts: Contact[]
  addresses: Address[]
  created_at: string | null
}

/* -------------------------------------------------------------------------- */
/* Payloads                                                                    */
/* -------------------------------------------------------------------------- */

/** Card fields shared by create and update (the registry data itself). */
export interface PersonalDataFields {
  type: PersonalDataType
  first_name?: string | null
  last_name?: string | null
  company_name?: string | null
  tax_code?: string | null
  vat_number?: string | null
  sdi_code?: string | null
  birth_date?: string | null
  gender?: Gender | null
}

/** POST /api/personal-data — card fields plus the owner. */
export type CreatePersonalDataPayload = PersonalDataFields & {
  personable_type: string
  personable_id: number
}

/** PUT /api/personal-data/{id} — full replacement; the owner is immutable. */
export type UpdatePersonalDataPayload = PersonalDataFields

/** Contact fields shared by create and update. */
export interface ContactFields {
  type: string
  value: string
  label?: string | null
  is_primary?: boolean
}

/** POST /api/contacts — contact fields plus the owner. */
export type CreateContactPayload = ContactFields & {
  contactable_type: string
  contactable_id: number
}

/** PUT /api/contacts/{id} — full replacement; the owner is immutable. */
export type UpdateContactPayload = ContactFields

/** Address fields shared by create and update. */
export interface AddressFields {
  line1: string
  line2?: string | null
  postal_code?: string | null
  city_id?: number | null
  province_id?: number | null
  state_id?: number | null
  country_id?: number | null
  latitude?: string | null
  longitude?: string | null
  is_primary?: boolean
}

/** POST /api/addresses — address fields plus the owner. */
export type CreateAddressPayload = AddressFields & {
  addressable_type: string
  addressable_id: number
}

/** PUT /api/addresses/{id} — full replacement; the owner is immutable. */
export type UpdateAddressPayload = AddressFields

/* -------------------------------------------------------------------------- */
/* Client-side drafts (buffered / controlled editing)                          */
/* -------------------------------------------------------------------------- */

/**
 * Client-side drafts used by the controlled personal-data components. Unlike the
 * wire payloads above (which persist one entity at a time through the per-entity
 * endpoints), drafts are held in a parent-owned buffer and submitted as a single
 * nested tree inside the user create/edit request (ADR 0012 / spec 0003).
 *
 * Each child draft carries:
 * - an optional `id` — present means an existing persisted row to update, absent
 *   means a brand new row to create;
 * - a stable client-only `_key` for React list rendering, since new rows have no
 *   id yet. The `_key` never leaves the client.
 */

/** A buffered contact channel awaiting submission inside the user payload. */
export interface ContactDraft {
  /** Stable client-only key for list rendering (never sent to the server). */
  _key: string
  /** Present = existing row to update; absent = new row to create. */
  id?: number
  type: string
  value: string
  label: string | null
  is_primary: boolean
}

/** A buffered address awaiting submission inside the user payload. */
export interface AddressDraft {
  /** Stable client-only key for list rendering (never sent to the server). */
  _key: string
  /** Present = existing row to update; absent = new row to create. */
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

/* -------------------------------------------------------------------------- */
/* Field-permission gating (spec 0008)                                        */
/* -------------------------------------------------------------------------- */

/**
 * Per-field/section gating for the shared personal-data components. Deliberately
 * NOT `@/features/authorization`'s `FieldPermission`: these owner-agnostic
 * components must stay decoupled from any specific resource (D3) — the caller
 * (e.g. the users feature, via `useResourcePermissions()`) adapts its resolved
 * permissions to this shape and injects it by prop. `hidden` is omitted: the
 * components only ever need `visible` to decide whether to render at all.
 */
export interface PersonalDataFieldPermission {
  visible: boolean
  editable: boolean
  required: boolean
  disabled: boolean
  readonly: boolean
}

/**
 * Resolves a dot-path field or section key (e.g. `personal_data.first_name`,
 * `personal_data.contacts`) to its gating. Optional on every consumer: omitting
 * it entirely preserves today's ungated behaviour (self-service profile, AC-013).
 */
export type PersonalDataFieldPermissionResolver = (
  fieldKey: string,
) => PersonalDataFieldPermission

/**
 * A buffered personal-data card with its owned contacts/addresses, owned by the
 * parent form. `null` (at the section level) means "no card entered".
 */
export interface PersonalDataDraft {
  /** Present = existing card to update; absent = new card to create. */
  id?: number
  type: PersonalDataType
  first_name: string | null
  last_name: string | null
  company_name: string | null
  tax_code: string | null
  vat_number: string | null
  sdi_code: string | null
  birth_date: string | null
  gender: Gender | null
  contacts: ContactDraft[]
  addresses: AddressDraft[]
}
