import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/products/product-form-payload'
import type { ProductDetail } from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

/** Spec 0017 AC-024: create payload shape, sparse PATCH of changed generic fields. */

function original(overrides: Partial<ProductDetail> = {}): ProductDetail {
  return {
    id: 5,
    name: 'ThinkPad X1',
    description: null,
    cost: 800,
    price: 1200,
    category_id: 3,
    category: { id: 3, name: 'Laptops' },
    product_type: 'SERVICE',
    created_at: '2026-01-01T00:00:00Z',
    vat_rate_id: null,
    vat_rate: null,
    supplier_id: null,
    supplier: null,
    ...overrides,
  }
}

function values(overrides: Partial<ProductFormValues> = {}): ProductFormValues {
  return {
    name: 'ThinkPad X1',
    description: null,
    cost: 800,
    price: 1200,
    category_id: 3,
    product_type: 'SERVICE',
    vat_rate_id: null,
    supplier_id: null,
    custom_fields: {},
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the create payload with the generic fields', () => {
    expect(buildCreatePayload(values())).toEqual({
      name: 'ThinkPad X1',
      description: null,
      cost: 800,
      price: 1200,
      category_id: 3,
      product_type: 'SERVICE',
      vat_rate_id: null,
      supplier_id: null,
    })
  })

  it('includes the selected VAT rate and supplier ids', () => {
    expect(buildCreatePayload(values({ vat_rate_id: 4, supplier_id: 11 }))).toMatchObject({
      vat_rate_id: 4,
      supplier_id: 11,
    })
  })
})

describe('buildUpdatePayload', () => {
  it('omits everything when nothing changed', () => {
    expect(buildUpdatePayload(values(), original())).toEqual({})
  })

  it('includes only the changed generic field', () => {
    expect(buildUpdatePayload(values({ name: 'ThinkPad X1 Gen 2' }), original())).toEqual({
      name: 'ThinkPad X1 Gen 2',
    })
  })

  it('includes only the changed VAT rate id', () => {
    expect(buildUpdatePayload(values({ vat_rate_id: 4 }), original())).toEqual({
      vat_rate_id: 4,
    })
  })

  it('includes only the changed supplier id', () => {
    expect(buildUpdatePayload(values({ supplier_id: 11 }), original())).toEqual({
      supplier_id: 11,
    })
  })
})
