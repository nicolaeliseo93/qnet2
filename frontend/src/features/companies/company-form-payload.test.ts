import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/companies/company-form-payload'
import type { CompanyDetailWithPermissions } from '@/features/companies/types'
import type { CompanyFormValues } from '@/features/companies/use-company-form'

/** Spec 0010 AC-016: create payload shape, update payload diffs only changes. */

const EMPTY_ADDRESS: CompanyFormValues['address'] = {
  line1: '',
  line2: '',
  postal_code: '',
  country_id: null,
  state_id: null,
  province_id: null,
  city_id: null,
}

const formValues: CompanyFormValues = {
  denomination: 'Acme Srl',
  vat_number: 'IT12345678901',
  address: {
    line1: '221B Baker Street',
    line2: '',
    postal_code: '20100',
    country_id: 1,
    state_id: 10,
    province_id: 50,
    city_id: 100,
  },
  custom_fields: {},
}

function original(
  overrides: Partial<CompanyDetailWithPermissions> = {},
): CompanyDetailWithPermissions {
  return {
    id: 7,
    denomination: 'Acme Srl',
    vat_number: 'IT12345678901',
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
  it('includes denomination, vat_number and the address block when filled', () => {
    const payload = buildCreatePayload(formValues)

    expect(payload.denomination).toBe('Acme Srl')
    expect(payload.vat_number).toBe('IT12345678901')
    expect(payload.address).toEqual({
      line1: '221B Baker Street',
      line2: null,
      postal_code: '20100',
      country_id: 1,
      state_id: 10,
      province_id: 50,
      city_id: 100,
    })
  })

  it('sends vat_number: null when left blank', () => {
    const payload = buildCreatePayload({ ...formValues, vat_number: '' })

    expect(payload.vat_number).toBeNull()
  })

  it('omits the address entirely when every field is left blank', () => {
    const payload = buildCreatePayload({ ...formValues, address: EMPTY_ADDRESS })

    expect('address' in payload).toBe(false)
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const payload = buildUpdatePayload(formValues, original())

    expect(payload).toEqual({})
  })

  it('includes only the changed denomination', () => {
    const payload = buildUpdatePayload(
      { ...formValues, denomination: 'Acme Srl EU' },
      original(),
    )

    expect(payload).toEqual({ denomination: 'Acme Srl EU' })
  })

  it('sends vat_number: null when cleared from a previous value', () => {
    const payload = buildUpdatePayload({ ...formValues, vat_number: '' }, original())

    expect(payload).toEqual({ vat_number: null })
  })

  it('includes the address only when it actually changed', () => {
    const unchanged = buildUpdatePayload(formValues, original())
    expect('address' in unchanged).toBe(false)

    const changed = buildUpdatePayload(
      { ...formValues, address: { ...formValues.address, postal_code: '20121' } },
      original(),
    )
    expect(changed.address).toEqual({
      line1: '221B Baker Street',
      line2: null,
      postal_code: '20121',
      country_id: 1,
      state_id: 10,
      province_id: 50,
      city_id: 100,
    })
  })

  it('sends a brand new address when the original company had none', () => {
    const payload = buildUpdatePayload(formValues, original({ address: null }))

    expect(payload.address).toEqual({
      line1: '221B Baker Street',
      line2: null,
      postal_code: '20100',
      country_id: 1,
      state_id: 10,
      province_id: 50,
      city_id: 100,
    })
  })

  it('does not send an (invalid) empty address when the user clears an existing one', () => {
    const payload = buildUpdatePayload({ ...formValues, address: EMPTY_ADDRESS }, original())

    expect('address' in payload).toBe(false)
  })
})
