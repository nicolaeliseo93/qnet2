import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/products/product-form-payload'
import type { ProductDetail } from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

/** Spec 0017 AC-024: create payload shape, sparse PATCH of changed generic fields + attributes when touched. */

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
    attributes: [
      { attribute_id: 9, code: 'ram', name: 'RAM', data_type: 'INTEGER', value: 16 },
    ],
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the create payload with generic fields + attributes array', () => {
    const values: ProductFormValues = {
      name: 'ThinkPad X1',
      description: null,
      cost: 800,
      price: 1200,
      category_id: 3,
      product_type: 'SERVICE',
      attributes: { '9': 16 },
    }

    expect(buildCreatePayload(values)).toEqual({
      name: 'ThinkPad X1',
      description: null,
      cost: 800,
      price: 1200,
      category_id: 3,
      product_type: 'SERVICE',
      attributes: [{ attribute_id: 9, value: 16 }],
    })
  })
})

describe('buildUpdatePayload', () => {
  it('omits everything when nothing changed and attributes were not touched', () => {
    const values: ProductFormValues = {
      name: 'ThinkPad X1',
      description: null,
      cost: 800,
      price: 1200,
      category_id: 3,
      product_type: 'SERVICE',
      attributes: { '9': 16 },
    }

    expect(buildUpdatePayload(values, original(), false)).toEqual({})
  })

  it('includes only the changed generic field', () => {
    const values: ProductFormValues = {
      name: 'ThinkPad X1 Gen 2',
      description: null,
      cost: 800,
      price: 1200,
      category_id: 3,
      product_type: 'SERVICE',
      attributes: { '9': 16 },
    }

    expect(buildUpdatePayload(values, original(), false)).toEqual({ name: 'ThinkPad X1 Gen 2' })
  })

  it('includes attributes only when they were touched, even if unchanged in value', () => {
    const values: ProductFormValues = {
      name: 'ThinkPad X1',
      description: null,
      cost: 800,
      price: 1200,
      category_id: 3,
      product_type: 'SERVICE',
      attributes: { '9': 32 },
    }

    expect(buildUpdatePayload(values, original(), true)).toEqual({
      attributes: [{ attribute_id: 9, value: 32 }],
    })
  })
})
