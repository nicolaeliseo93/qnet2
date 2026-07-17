import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/vat-rates/vat-rate-form-payload'
import type { VatRateDetailWithPermissions } from '@/features/vat-rates/types'
import type { VatRateFormValues } from '@/features/vat-rates/use-vat-rate-form'

/** Mirrors `source-form-payload.test.ts` (spec 0018): create shape, update diffs only changes. */

const formValues: VatRateFormValues = { name: 'Standard', rate: 22, custom_fields: {} }

function original(overrides: Partial<VatRateDetailWithPermissions> = {}): VatRateDetailWithPermissions {
  return {
    id: 7,
    name: 'Standard',
    rate: 22,
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
  it('builds the full create payload shape', () => {
    expect(buildCreatePayload(formValues)).toEqual({ name: 'Standard', rate: 22 })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ name: 'Reduced', rate: 22, custom_fields: {} }, original())).toEqual({
      name: 'Reduced',
    })
  })

  it('includes only the changed rate', () => {
    expect(buildUpdatePayload({ name: 'Standard', rate: 10, custom_fields: {} }, original())).toEqual({
      rate: 10,
    })
  })
})
