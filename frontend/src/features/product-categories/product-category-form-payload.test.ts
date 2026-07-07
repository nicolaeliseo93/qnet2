import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/product-categories/product-category-form-payload'
import type { ProductCategoryDetail } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** Spec 0017 AC-010: create shape, update diffs generic fields + full-replace attributes sync. */

function original(overrides: Partial<ProductCategoryDetail> = {}): ProductCategoryDetail {
  return {
    id: 4,
    name: 'Laptops',
    parent_id: 1,
    parent: { id: 1, name: 'Electronics' },
    description: null,
    attributes: [{ attribute_id: 9, code: 'ram', name: 'RAM', data_type: 'INTEGER', is_required: true, sort_order: 0 }],
    inherited_attributes: [],
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the full create payload shape', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      description: null,
      attributes: [{ attribute_id: 9, is_required: true, sort_order: 0 }],
    }

    expect(buildCreatePayload(values)).toEqual({
      name: 'Laptops',
      parent_id: 1,
      description: null,
      attributes: [{ attribute_id: 9, is_required: true, sort_order: 0 }],
    })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      description: null,
      attributes: [{ attribute_id: 9, is_required: true, sort_order: 0 }],
    }

    expect(buildUpdatePayload(values, original())).toEqual({})
  })

  it('includes only the changed parent_id', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 2,
      description: null,
      attributes: [{ attribute_id: 9, is_required: true, sort_order: 0 }],
    }

    expect(buildUpdatePayload(values, original())).toEqual({ parent_id: 2 })
  })

  it('sends a full attributes replacement when the assignment set changed', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      description: null,
      attributes: [{ attribute_id: 9, is_required: false, sort_order: 0 }],
    }

    expect(buildUpdatePayload(values, original())).toEqual({
      attributes: [{ attribute_id: 9, is_required: false, sort_order: 0 }],
    })
  })
})
