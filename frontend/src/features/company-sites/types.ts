/**
 * Company Sites CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely company-sites-specific — the resource, its embedded personal-data
 * card (identity + contacts + single address), its inline banks collection and
 * its create/update payloads. Source of truth: the frozen spec 0020
 * `data_contract`; the nested `personal_data` sub-contract is reused unchanged
 * from `features/personal-data` (mirrors the Registries module).
 */

import type { PersonalDataCard } from '@/features/personal-data/types'
import type { PersonalDataPayload } from '@/features/personal-data/drafts'
import type { ResourcePermissions } from '@/features/authorization/types'

/** A single bank of the site's inline 1→N banks collection. */
export interface CompanySiteBank {
  id: number
  name: string
  iban: string | null
  notes: string | null
  /** The site's preferred bank (single-primary, mirrors contacts/addresses). */
  is_primary: boolean
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
  notes: string | null
  is_default: boolean
  logo_url: string | null
  /**
   * The site's personal-data card (identity + contacts + at most one address).
   * `null` only in the pathological case of a site with no card yet.
   */
  personal_data: PersonalDataCard | null
  banks: CompanySiteBank[]
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
  /** The company (società) this site belongs to, editable in the Impostazioni tab. */
  company: { id: number; label: string } | null
  // "Altro" section (read-only in this spec): plain ids/scalars, no nested refs.
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

/** A single bank row accepted by POST/PATCH /company-sites (`banks[]`). */
export interface CreateCompanySiteBankPayload {
  /** Present = existing row to update; absent = new row to create. */
  id?: number
  name: string
  iban?: string | null
  notes?: string | null
  is_primary: boolean
}

/**
 * Payload for POST /company-sites (create). `name` is the site's own required
 * scalar (NOT derived from the card); `personal_data` is REQUIRED and always
 * carries `type: 'company'` with at most one address (spec 0020, mirrors the
 * Registries `personal_data` envelope).
 */
export interface CreateCompanySitePayload {
  name: string
  notes?: string | null
  personal_data: PersonalDataPayload
  banks?: CreateCompanySiteBankPayload[]
  company_id?: number | null
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
 * `personal_data` is a full-replace sync of the card + contacts + single
 * address, a present `banks` is the AUTHORITATIVE list (add/update/delete
 * diff, resolved server-side by `BankService::sync`). "Altro" fields and
 * `is_default` are never sent (read-only / dedicated `set-default` action).
 */
export interface UpdateCompanySitePayload {
  name?: string
  notes?: string | null
  personal_data?: PersonalDataPayload
  banks?: CreateCompanySiteBankPayload[]
  company_id?: number | null
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
  /** The site's preferred bank (single-primary across the list). */
  is_primary: boolean
}
