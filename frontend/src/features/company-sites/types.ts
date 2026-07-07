/**
 * Company Sites CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely company-sites-specific — the resource, its polymorphic address,
 * its inline banks collection and its create/update payloads. Source of
 * truth: the frozen spec 0020 `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** The site's single address (polymorphic, same cascade as companies). */
export interface CompanySiteAddress {
  id: number
  line1: string
  line2: string | null
  postal_code: string | null
  country_id: number | null
  state_id: number | null
  province_id: number | null
  city_id: number | null
  country: string | null
  region: string | null
  province: string | null
  city: string | null
  is_primary: boolean
}

/** A single bank of the site's inline 1→N banks collection. */
export interface CompanySiteBank {
  id: number
  name: string
  iban: string | null
  notes: string | null
}

/** A related user reference as returned inside a `responsible_*` field. */
export interface CompanySiteResponsibleRef {
  id: number
  label: string
}

/**
 * Single company-site detail returned by GET/POST/PATCH /company-sites
 * (envelope `data`). Matches `CompanySiteResource`.
 */
export interface CompanySiteDetail {
  id: number
  name: string
  email: string
  fiscal_code: string | null
  vat_number: string | null
  phone: string | null
  pec: string | null
  fax: string | null
  notes: string | null
  is_default: boolean
  logo_url: string | null
  address: CompanySiteAddress | null
  banks: CompanySiteBank[]
  default_bank_id: number | null
  responsible_rda_id: number | null
  responsible_rda: CompanySiteResponsibleRef | null
  responsible_tickets_id: number | null
  responsible_tickets: CompanySiteResponsibleRef | null
  responsible_validation_contracts_id: number | null
  responsible_validation_contracts: CompanySiteResponsibleRef | null
  responsible_validation_contracts_two_id: number | null
  responsible_validation_contracts_two: CompanySiteResponsibleRef | null
  proforma_progressive: number | null
  invoice_progressive: number | null
  quotation_layout_id: number | null
  quotation_header_id: number | null
  quotation_footer_id: number | null
  // "Altro" section (read-only in this spec): plain ids/scalars, no nested refs.
  company_id: number | null
  accounting_manager_id: number | null
  store_id: number | null
  company_type: number | null
  commissions: number | null
  order_sites: number | null
  payment_status_assign_technician: number | null
  payment_status_deposit: number | null
  payment_status_balance: number | null
  default_payment_id: number | null
  default_vat_id: number | null
  other_category_id: number | null
  iso_category_id: number | null
  soa_category_id: number | null
  sic_category_id: number | null
  avv_category_id: number | null
  gdpr_category_id: number | null
  res_category_id: number | null
  pal_category_id: number | null
  quattro_category_id: number | null
  finage_category_id: number | null
  fondi_category_id: number | null
  gare_category_id: number | null
  partnership_category_id: number | null
  progetti_category_id: number | null
  status: number | null
  color: string | null
  surface_sqm: number | null
  created_at: string | null
}

/**
 * A `CompanySiteDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /company-sites/{companySite}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface CompanySiteDetailWithPermissions extends CompanySiteDetail {
  permissions: ResourcePermissions
}

/** The nested address block accepted by POST/PATCH /company-sites. */
export interface CreateCompanySiteAddressPayload {
  line1: string
  line2?: string | null
  postal_code?: string | null
  country_id?: number | null
  state_id?: number | null
  province_id?: number | null
  city_id?: number | null
}

/** A single bank row accepted by POST/PATCH /company-sites (`banks[]`). */
export interface CreateCompanySiteBankPayload {
  /** Present = existing row to update; absent = new row to create. */
  id?: number
  name: string
  iban?: string | null
  notes?: string | null
}

/** Payload for POST /company-sites (create). */
export interface CreateCompanySitePayload {
  name: string
  email: string
  fiscal_code?: string | null
  vat_number?: string | null
  phone?: string | null
  pec?: string | null
  fax?: string | null
  notes?: string | null
  address?: CreateCompanySiteAddressPayload
  banks?: CreateCompanySiteBankPayload[]
  default_bank_id?: number | null
  responsible_rda_id?: number | null
  responsible_tickets_id?: number | null
  responsible_validation_contracts_id?: number | null
  responsible_validation_contracts_two_id?: number | null
  proforma_progressive?: number | null
  invoice_progressive?: number | null
}

/**
 * Payload for PATCH /company-sites/{id} (partial update). Every field is
 * optional so the request only carries what actually changed; a present
 * `address` fully rewrites the site's single address, a present `banks` is
 * the AUTHORITATIVE list (add/update/delete diff, resolved server-side by
 * `BankService::sync`). "Altro" fields and `is_default` are never sent (read-
 * only / dedicated `set-default` action).
 */
export interface UpdateCompanySitePayload {
  name?: string
  email?: string
  fiscal_code?: string | null
  vat_number?: string | null
  phone?: string | null
  pec?: string | null
  fax?: string | null
  notes?: string | null
  address?: CreateCompanySiteAddressPayload
  banks?: CreateCompanySiteBankPayload[]
  default_bank_id?: number | null
  responsible_rda_id?: number | null
  responsible_tickets_id?: number | null
  responsible_validation_contracts_id?: number | null
  responsible_validation_contracts_two_id?: number | null
  proforma_progressive?: number | null
  invoice_progressive?: number | null
}

/**
 * A buffered bank row awaiting submission inside the site payload (mirrors
 * `ContactDraft`): an optional `id` (present = existing row to update, absent
 * = new row to create) plus a stable client-only `_key` for list rendering.
 */
export interface BankDraft {
  /** Stable client-only key for list rendering (never sent to the server). */
  _key: string
  id?: number
  name: string
  iban: string | null
  notes: string | null
}
