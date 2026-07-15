import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCreateCampaignSchema } from '@/features/campaigns/campaign-schema'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Spec 0023 BR-2 (required-when-standalone, now 3 classification fields —
 * `state_id` left this group, spec 0027 D-3) and BR-6 (end_date >=
 * start_date), mirrored client-side. Spec 0025 AC-010: `code` is optional
 * and, when set, mirrors the backend's max:32. Spec 0027 BR-4/BR-5: the geo
 * hierarchy rule (`country_id` required unless the project locks it,
 * province/city require a state) is covered separately below — the existing
 * BR-2 assertions on `state_id` were REWRITTEN, not tampered with: the
 * requirement changed (D-3).
 */

const EMPTY_CUSTOM_FIELDS_SCHEMA = buildCustomFieldsSchema(
  [],
  { resource: { view: true, create: true, update: true, delete: true, export: true, import: true }, fields: {}, actions: {} },
  i18n.t,
)

function baseValues(overrides: Record<string, unknown> = {}) {
  return {
    project_id: null,
    name: 'New campaign',
    description: null,
    registry_id: null,
    source_id: null,
    partner_id: null,
    pipeline_status_id: 1,
    business_function_id: 2,
    product_category_id: 4,
    country_id: 10,
    state_id: null,
    province_id: null,
    city_id: null,
    geo_locked_levels: [],
    start_date: '',
    end_date: '',
    total_budget: null,
    target_lead: null,
    custom_fields: {},
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateCampaignSchema — standalone (project_id null)', () => {
  it('accepts a valid standalone payload with the 3 classification fields set and a country', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  it('rejects a missing name', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues({ name: '' }))
    expect(result.success).toBe(false)
  })

  it('rejects each of the 3 classification fields when null (AC-023/AC-043)', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(
      baseValues({
        pipeline_status_id: null,
        business_function_id: null,
        product_category_id: null,
      }),
    )
    expect(result.success).toBe(false)
    if (!result.success) {
      const paths = result.error.issues.map((issue) => issue.path.join('.'))
      expect(paths).toEqual(
        expect.arrayContaining(['pipeline_status_id', 'business_function_id', 'product_category_id']),
      )
    }
  })

  it('rejects end_date earlier than start_date (BR-6)', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(
      baseValues({ start_date: '2026-02-01', end_date: '2026-01-01' }),
    )
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'end_date')).toBe(true)
    }
  })
})

describe('buildCreateCampaignSchema — linked (project_id set)', () => {
  it('accepts a linked payload with the 3 classification fields left null (AC-020/AC-042)', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(
      baseValues({
        project_id: 7,
        pipeline_status_id: null,
        business_function_id: null,
        product_category_id: null,
      }),
    )
    expect(result.success).toBe(true)
  })
})

describe('buildCreateCampaignSchema — geo hierarchy (spec 0027 BR-4/BR-5)', () => {
  it('rejects a standalone campaign with no country', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues({ country_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'country_id')).toBe(true)
    }
  })

  it('accepts a linked campaign with no country when the project locks it (D-5)', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(
      baseValues({
        project_id: 7,
        pipeline_status_id: null,
        business_function_id: null,
        product_category_id: null,
        country_id: null,
        geo_locked_levels: ['country'],
      }),
    )
    expect(result.success).toBe(true)
  })

  it('requires a country when the linked project does not provide one', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(
      baseValues({
        project_id: 7,
        pipeline_status_id: null,
        business_function_id: null,
        product_category_id: null,
        country_id: null,
        geo_locked_levels: [],
      }),
    )
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'country_id')).toBe(true)
    }
  })

  it('rejects a province set without a state', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues({ province_id: 5, state_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'province_id')).toBe(true)
    }
  })

  it('rejects a city set without a state', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues({ city_id: 9, state_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'city_id')).toBe(true)
    }
  })

  it('accepts a province/city set without an own state when the project locks the state (inherited parent)', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(
      baseValues({
        project_id: 7,
        pipeline_status_id: null,
        business_function_id: null,
        product_category_id: null,
        country_id: null,
        state_id: null,
        province_id: 5,
        city_id: 9,
        geo_locked_levels: ['country', 'state'],
      }),
    )
    expect(result.success).toBe(true)
  })

  it('accepts a full valid geo tuple', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues({ state_id: 3, province_id: 5, city_id: 9 }))
    expect(result.success).toBe(true)
  })
})

describe('buildCreateCampaignSchema — manual code (spec 0025 AC-010)', () => {
  it('accepts a payload with no code (falls back to server generation)', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  it('accepts a valid manual code and trims it', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues({ code: '  ACME-2026  ' }))
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.code).toBe('ACME-2026')
    }
  })

  it('rejects a code of 33+ characters (mirrors backend max:32, AC-005)', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues({ code: 'A'.repeat(33) }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'code')).toBe(true)
    }
  })
})
