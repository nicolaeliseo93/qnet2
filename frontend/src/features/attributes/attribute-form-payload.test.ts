import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/attributes/attribute-form-payload'
import type { AttributeDetail } from '@/features/attributes/types'
import type { AttributeFormValues } from '@/features/attributes/use-attribute-form'

/** Spec 0017 AC-003/AC-004: create shape, options only for ENUM, update diffs only changes. */

function original(overrides: Partial<AttributeDetail> = {}): AttributeDetail {
  return {
    id: 7,
    code: 'color',
    name: 'Color',
    data_type: 'ENUM',
    options: [
      { id: 1, value: 'red', label: 'Red', sort_order: 0 },
      { id: 2, value: 'blue', label: 'Blue', sort_order: 1 },
    ],
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('omits options for a non-ENUM attribute', () => {
    const values: AttributeFormValues = {
      code: 'weight',
      name: 'Weight',
      data_type: 'DECIMAL',
      options: [],
    }

    expect(buildCreatePayload(values)).toEqual({
      code: 'weight',
      name: 'Weight',
      data_type: 'DECIMAL',
      options: undefined,
    })
  })

  it('includes options with a positional sort_order for an ENUM attribute', () => {
    const values: AttributeFormValues = {
      code: 'color',
      name: 'Color',
      data_type: 'ENUM',
      options: [
        { value: 'red', label: 'Red' },
        { value: 'blue', label: 'Blue' },
      ],
    }

    expect(buildCreatePayload(values)).toEqual({
      code: 'color',
      name: 'Color',
      data_type: 'ENUM',
      options: [
        { value: 'red', label: 'Red', sort_order: 0 },
        { value: 'blue', label: 'Blue', sort_order: 1 },
      ],
    })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const values: AttributeFormValues = {
      code: 'color',
      name: 'Color',
      data_type: 'ENUM',
      options: [
        { value: 'red', label: 'Red' },
        { value: 'blue', label: 'Blue' },
      ],
    }

    expect(buildUpdatePayload(values, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    const values: AttributeFormValues = {
      code: 'color',
      name: 'Colour',
      data_type: 'ENUM',
      options: [
        { value: 'red', label: 'Red' },
        { value: 'blue', label: 'Blue' },
      ],
    }

    expect(buildUpdatePayload(values, original())).toEqual({ name: 'Colour' })
  })

  it('sends a full option replacement when the option set changed', () => {
    const values: AttributeFormValues = {
      code: 'color',
      name: 'Color',
      data_type: 'ENUM',
      options: [{ value: 'red', label: 'Red' }],
    }

    expect(buildUpdatePayload(values, original())).toEqual({
      options: [{ value: 'red', label: 'Red', sort_order: 0 }],
    })
  })
})
