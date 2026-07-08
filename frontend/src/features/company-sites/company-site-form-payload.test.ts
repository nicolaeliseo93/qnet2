import { describe, expect, it } from 'vitest'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataCard, PersonalDataDraft } from '@/features/personal-data/types'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/company-sites/company-site-form-payload'
import type { BankDraft, CompanySiteDetailWithPermissions } from '@/features/company-sites/types'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'

/**
 * Spec 0020: the create payload keeps `name` as the site's own scalar and
 * always carries the nested `personal_data` tree (type `company`, at most one
 * address); the update payload diffs only what changed, including the buffered
 * anagraphic card.
 */

/** A company card draft matching `card()` below, so defaults compare equal. */
function companyDraft(overrides: Partial<PersonalDataDraft> = {}): PersonalDataDraft {
  return {
    ...emptyPersonalDataDraft('company'),
    company_name: 'ACME S.p.A.',
    vat_number: 'IT12345678901',
    tax_code: 'ACMFSC80A01H501U',
    ...overrides,
  }
}

function card(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
  return {
    id: 99,
    type: 'company',
    first_name: null,
    last_name: null,
    company_name: 'ACME S.p.A.',
    full_name: 'ACME S.p.A.',
    ceo: null,
    tax_code: 'ACMFSC80A01H501U',
    vat_number: 'IT12345678901',
    sdi_code: null,
    birth_date: null,
    gender: null,
    personable_type: 'company_site',
    personable_id: 7,
    contacts: [],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

const formValues: CompanySiteFormValues = {
  name: 'Sede Nord',
  notes: '',
  responsible_rda_id: 7,
  responsible_tickets_id: null,
  responsible_validation_contracts_id: null,
  responsible_validation_contracts_two_id: null,
  default_bank_id: null,
  proforma_progressive: 1,
  invoice_progressive: 1,
}

const banks: BankDraft[] = [
  { _key: 'bank-1', id: 1, name: 'Banca Test', iban: 'IT60X0542811101000000123456', notes: null },
]

function original(
  overrides: Partial<CompanySiteDetailWithPermissions> = {},
): CompanySiteDetailWithPermissions {
  return {
    id: 7,
    name: 'Sede Nord',
    notes: null,
    is_default: false,
    logo_url: null,
    personal_data: card(),
    banks: [{ id: 1, name: 'Banca Test', iban: 'IT60X0542811101000000123456', notes: null }],
    default_bank_id: null,
    responsible_rda_id: 7,
    responsible_rda: { id: 7, label: 'Ada Lovelace' },
    responsible_tickets_id: null,
    responsible_tickets: null,
    responsible_validation_contracts_id: null,
    responsible_validation_contracts: null,
    responsible_validation_contracts_two_id: null,
    responsible_validation_contracts_two: null,
    proforma_progressive: 1,
    invoice_progressive: 1,
    quotation_layout_id: null,
    quotation_header_id: null,
    quotation_footer_id: null,
    company_id: null,
    accounting_manager_id: null,
    store_id: null,
    company_type: null,
    commissions: null,
    order_sites: null,
    payment_status_assign_technician: null,
    payment_status_deposit: null,
    payment_status_balance: null,
    default_payment_id: null,
    default_vat_id: null,
    other_category_id: null,
    iso_category_id: null,
    soa_category_id: null,
    sic_category_id: null,
    avv_category_id: null,
    gdpr_category_id: null,
    res_category_id: null,
    pal_category_id: null,
    quattro_category_id: null,
    finage_category_id: null,
    fondi_category_id: null,
    gare_category_id: null,
    partnership_category_id: null,
    progetti_category_id: null,
    status: null,
    color: null,
    surface_sqm: null,
    created_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('keeps name as the site scalar and always carries a company personal_data tree', () => {
    const payload = buildCreatePayload(formValues, banks, companyDraft())

    expect(payload.name).toBe('Sede Nord')
    expect(payload.personal_data.type).toBe('company')
    expect(payload.personal_data.company_name).toBe('ACME S.p.A.')
    expect(payload.personal_data.vat_number).toBe('IT12345678901')
    expect(payload.banks).toEqual([
      { id: 1, name: 'Banca Test', iban: 'IT60X0542811101000000123456', notes: null },
    ])
  })

  it('maps a blank notes string to null', () => {
    const payload = buildCreatePayload({ ...formValues, notes: '' }, banks, companyDraft())
    expect(payload.notes).toBeNull()
  })

  it('omits banks when the buffer is empty', () => {
    const payload = buildCreatePayload(formValues, [], companyDraft())
    expect('banks' in payload).toBe(false)
  })

  it('caps personal_data.addresses to a single entry', () => {
    const twoAddresses = companyDraft({
      addresses: [
        {
          _key: 'a1', line1: 'First', line2: null, postal_code: null, city_id: null,
          province_id: null, state_id: null, country_id: null, is_primary: true, site_type: 'legal_seat',
        },
        {
          _key: 'a2', line1: 'Second', line2: null, postal_code: null, city_id: null,
          province_id: null, state_id: null, country_id: null, is_primary: false, site_type: 'billing',
        },
      ],
    })
    const payload = buildCreatePayload(formValues, banks, twoAddresses)
    expect(payload.personal_data.addresses).toHaveLength(1)
    expect(payload.personal_data.addresses?.[0].line1).toBe('First')
  })

  it('never includes an "Altro" field or is_default', () => {
    const payload = buildCreatePayload(formValues, banks, companyDraft())
    expect('status' in payload).toBe(false)
    expect('company_id' in payload).toBe(false)
    expect('is_default' in payload).toBe(false)
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field, including personal_data, when nothing changed', () => {
    const payload = buildUpdatePayload(formValues, original(), banks, companyDraft())
    expect(payload).toEqual({})
  })

  it('includes only the changed name', () => {
    const payload = buildUpdatePayload(
      { ...formValues, name: 'Sede Nord EU' },
      original(),
      banks,
      companyDraft(),
    )
    expect(payload).toEqual({ name: 'Sede Nord EU' })
  })

  it('includes personal_data only when the buffered card actually differs', () => {
    const changed = buildUpdatePayload(
      formValues,
      original(),
      banks,
      companyDraft({ company_name: 'ACME International S.p.A.' }),
    )
    expect(changed.personal_data).toBeDefined()
    expect(changed.personal_data?.company_name).toBe('ACME International S.p.A.')
    expect(changed.name).toBeUndefined()
  })

  it('includes banks only when the buffer differs from the original collection (AC-019)', () => {
    const unchanged = buildUpdatePayload(formValues, original(), banks, companyDraft())
    expect('banks' in unchanged).toBe(false)

    const added: BankDraft[] = [...banks, { _key: 'bank-new', name: 'Nuova Banca', iban: null, notes: null }]
    const changed = buildUpdatePayload(formValues, original(), added, companyDraft())
    expect(changed.banks).toEqual([
      { id: 1, name: 'Banca Test', iban: 'IT60X0542811101000000123456', notes: null },
      { name: 'Nuova Banca', iban: null, notes: null },
    ])
  })

  it('includes banks when a row is removed from the buffer', () => {
    const changed = buildUpdatePayload(formValues, original(), [], companyDraft())
    expect(changed.banks).toEqual([])
  })

  it('never sends an "Altro" field or is_default even when the original differs', () => {
    const payload = buildUpdatePayload(
      formValues,
      original({ status: 3, company_id: 9 }),
      banks,
      companyDraft(),
    )
    expect('status' in payload).toBe(false)
    expect('company_id' in payload).toBe(false)
    expect('is_default' in payload).toBe(false)
  })
})
