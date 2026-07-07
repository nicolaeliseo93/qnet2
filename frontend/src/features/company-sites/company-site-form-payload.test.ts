import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/company-sites/company-site-form-payload'
import type { BankDraft, CompanySiteDetailWithPermissions } from '@/features/company-sites/types'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'

/** Spec 0020 AC-019: create payload shape, update payload diffs only changes. */

const EMPTY_ADDRESS: CompanySiteFormValues['address'] = {
  line1: '',
  line2: '',
  postal_code: '',
  country_id: null,
  state_id: null,
  province_id: null,
  city_id: null,
}

const formValues: CompanySiteFormValues = {
  name: 'Sede Nord',
  email: 'nord@acme.test',
  fiscal_code: 'ACMFSC80A01H501U',
  vat_number: 'IT12345678901',
  phone: '0123456789',
  pec: 'nord@pec.acme.test',
  fax: '',
  notes: '',
  address: {
    line1: '221B Baker Street',
    line2: '',
    postal_code: '20100',
    country_id: 1,
    state_id: 10,
    province_id: 50,
    city_id: 100,
  },
  responsible_rda_id: 7,
  responsible_tickets_id: null,
  responsible_validation_contracts_id: null,
  responsible_validation_contracts_two_id: null,
  default_bank_id: null,
  proforma_progressive: 1,
  invoice_progressive: 1,
}

const banks: BankDraft[] = [{ _key: 'bank-1', id: 1, name: 'Banca Test', iban: 'IT60X0542811101000000123456', notes: null }]

function original(
  overrides: Partial<CompanySiteDetailWithPermissions> = {},
): CompanySiteDetailWithPermissions {
  return {
    id: 7,
    name: 'Sede Nord',
    email: 'nord@acme.test',
    fiscal_code: 'ACMFSC80A01H501U',
    vat_number: 'IT12345678901',
    phone: '0123456789',
    pec: 'nord@pec.acme.test',
    fax: null,
    notes: null,
    is_default: false,
    logo_url: null,
    address: {
      id: 3,
      line1: '221B Baker Street',
      line2: null,
      postal_code: '20100',
      country_id: 1,
      state_id: 10,
      province_id: 50,
      city_id: 100,
      country: 'Italy',
      region: 'Lombardy',
      province: 'Milan',
      city: 'Milan',
      is_primary: true,
    },
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
  it('includes the scalar fields, the address and the banks buffer', () => {
    const payload = buildCreatePayload(formValues, banks)

    expect(payload.name).toBe('Sede Nord')
    expect(payload.email).toBe('nord@acme.test')
    expect(payload.address).toEqual({
      line1: '221B Baker Street',
      line2: null,
      postal_code: '20100',
      country_id: 1,
      state_id: 10,
      province_id: 50,
      city_id: 100,
    })
    expect(payload.banks).toEqual([
      { id: 1, name: 'Banca Test', iban: 'IT60X0542811101000000123456', notes: null },
    ])
  })

  it('sends null for blank optional scalars', () => {
    const payload = buildCreatePayload({ ...formValues, fax: '', notes: '' }, banks)
    expect(payload.fax).toBeNull()
    expect(payload.notes).toBeNull()
  })

  it('omits the address entirely when every field is left blank', () => {
    const payload = buildCreatePayload({ ...formValues, address: EMPTY_ADDRESS }, banks)
    expect('address' in payload).toBe(false)
  })

  it('omits banks when the buffer is empty', () => {
    const payload = buildCreatePayload(formValues, [])
    expect('banks' in payload).toBe(false)
  })

  it('never includes an "Altro" field or is_default', () => {
    const payload = buildCreatePayload(formValues, banks)
    expect('status' in payload).toBe(false)
    expect('company_id' in payload).toBe(false)
    expect('is_default' in payload).toBe(false)
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const payload = buildUpdatePayload(formValues, original(), banks)
    expect(payload).toEqual({})
  })

  it('includes only the changed name', () => {
    const payload = buildUpdatePayload({ ...formValues, name: 'Sede Nord EU' }, original(), banks)
    expect(payload).toEqual({ name: 'Sede Nord EU' })
  })

  it('includes the address only when it actually changed', () => {
    const unchanged = buildUpdatePayload(formValues, original(), banks)
    expect('address' in unchanged).toBe(false)

    const changed = buildUpdatePayload(
      { ...formValues, address: { ...formValues.address, postal_code: '20121' } },
      original(),
      banks,
    )
    expect(changed.address).toMatchObject({ postal_code: '20121' })
  })

  it('includes banks only when the buffer differs from the original collection (AC-019)', () => {
    const unchanged = buildUpdatePayload(formValues, original(), banks)
    expect('banks' in unchanged).toBe(false)

    const added: BankDraft[] = [...banks, { _key: 'bank-new', name: 'Nuova Banca', iban: null, notes: null }]
    const changed = buildUpdatePayload(formValues, original(), added)
    expect(changed.banks).toEqual([
      { id: 1, name: 'Banca Test', iban: 'IT60X0542811101000000123456', notes: null },
      { name: 'Nuova Banca', iban: null, notes: null },
    ])
  })

  it('includes banks when a row is removed from the buffer', () => {
    const changed = buildUpdatePayload(formValues, original(), [])
    expect(changed.banks).toEqual([])
  })

  it('never sends an "Altro" field or is_default even when the original differs', () => {
    const payload = buildUpdatePayload(formValues, original({ status: 3, company_id: 9 }), banks)
    expect('status' in payload).toBe(false)
    expect('company_id' in payload).toBe(false)
    expect('is_default' in payload).toBe(false)
  })
})
