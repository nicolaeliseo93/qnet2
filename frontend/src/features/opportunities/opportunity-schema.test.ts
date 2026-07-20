import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCreateOpportunitySchema } from '@/features/opportunities/opportunity-schema'

/**
 * AC-070: `name` and `registry_id` are mandatory (D-4); every other field is
 * optional; probability out of 0..100 rejected.
 */

function baseValues(overrides: Record<string, unknown> = {}) {
  return {
    name: 'Enterprise deal',
    registry_id: 1,
    // Spec 0043 D-3: opportunity_status_id is mandatory, mirrors registry_id.
    opportunity_status_id: 5,
    referent_id: null,
    commercial_id: null,
    reporter_id: null,
    supervisor_id: null,
    source_id: null,
    // product_lines is mandatory (>=1 row, user directive 2026-07-17): the base
    // happy-path carries one valid row; the empty-collection case overrides it.
    product_lines: [{ business_function_id: 1, product_category_id: 11 }],
    manager_slots: [],
    start_date: null,
    expected_close_date: null,
    estimated_value: null,
    // A-6: rendered as a slider that always holds a value (default 0), never null.
    success_probability: 0,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateOpportunitySchema', () => {
  it('accepts a valid payload with only the 2 mandatory fields set', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  it('rejects a missing name', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ name: '' }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'name')).toBe(true)
    }
  })

  it('rejects a missing registry_id', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ registry_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'registry_id')).toBe(true)
    }
  })

  /** Spec 0043 D-3: opportunity_status_id is a mandatory FK, mirrors registry_id. */
  it('rejects a missing opportunity_status_id', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ opportunity_status_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(
        result.error.issues.some((issue) => issue.path.join('.') === 'opportunity_status_id'),
      ).toBe(true)
    }
  })

  it('accepts every truly optional relation left null', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  /**
   * Amendment rev.3 (AC-097/099/106): `product_lines` replaces the single
   * business_function_id/product_category_id fields with an inline-editable
   * row collection (mirrors `manager_slots`: "Add" appends an empty row).
   * Each id is individually nullable, but a `superRefine` requires BOTH
   * non-null per row before submit.
   */
  describe('product_lines (amendment rev.3)', () => {
    it('rejects an empty collection (user directive 2026-07-17: at least one row required)', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(baseValues({ product_lines: [] }))
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
      }
    })

    it('accepts one or more complete rows', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(
        baseValues({
          product_lines: [
            { business_function_id: 1, product_category_id: 11 },
            { business_function_id: 2, product_category_id: 22 },
          ],
        }),
      )
      expect(result.success).toBe(true)
    })

    it('rejects a freshly-added empty row (both ids null)', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(
        baseValues({ product_lines: [{ business_function_id: null, product_category_id: null }] }),
      )
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
      }
    })

    it('rejects a row missing only business_function_id', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(
        baseValues({ product_lines: [{ business_function_id: null, product_category_id: 11 }] }),
      )
      expect(result.success).toBe(false)
    })

    it('rejects a row missing only product_category_id', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(
        baseValues({ product_lines: [{ business_function_id: 1, product_category_id: null }] }),
      )
      expect(result.success).toBe(false)
    })

    it('rejects the whole collection when only one of several rows is incomplete', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(
        baseValues({
          product_lines: [
            { business_function_id: 1, product_category_id: 11 },
            { business_function_id: 2, product_category_id: null },
          ],
        }),
      )
      expect(result.success).toBe(false)
    })
  })

  it('accepts every truly optional field when set', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(
      baseValues({
        referent_id: 6,
        commercial_id: 7,
        reporter_id: 8,
        supervisor_id: 9,
        source_id: 10,
        product_lines: [{ business_function_id: 5, product_category_id: 11 }],
        manager_slots: [9, null, 12],
        start_date: '2026-01-01',
        expected_close_date: '2026-06-30',
        estimated_value: 15000.5,
        success_probability: 60,
      }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects success_probability above 100', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ success_probability: 101 }))
    expect(result.success).toBe(false)
  })

  it('rejects success_probability below 0', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ success_probability: -1 }))
    expect(result.success).toBe(false)
  })

  it('accepts success_probability at the 0 and 100 boundaries', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    expect(schema.safeParse(baseValues({ success_probability: 0 })).success).toBe(true)
    expect(schema.safeParse(baseValues({ success_probability: 100 })).success).toBe(true)
  })

  it('rejects a negative estimated_value', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ estimated_value: -1 }))
    expect(result.success).toBe(false)
  })

  it('rejects more than 4 filled manager slots', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ manager_slots: [1, 2, 3, 4, 5] }))
    expect(result.success).toBe(false)
  })
})
