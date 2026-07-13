import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCreateCampaignSchema } from '@/features/campaigns/campaign-schema'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'

/** Spec 0023 BR-2 (required-when-standalone) and BR-6 (end_date >= start_date), mirrored client-side. */

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
    project_status_id: 1,
    business_function_id: 2,
    state_id: 3,
    product_category_id: 4,
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
  it('accepts a valid standalone payload with all 4 classification fields set', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  it('rejects a missing name', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues({ name: '' }))
    expect(result.success).toBe(false)
  })

  it('rejects each of the 4 classification fields when null (AC-023/AC-043)', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(
      baseValues({
        project_status_id: null,
        business_function_id: null,
        state_id: null,
        product_category_id: null,
      }),
    )
    expect(result.success).toBe(false)
    if (!result.success) {
      const paths = result.error.issues.map((issue) => issue.path.join('.'))
      expect(paths).toEqual(
        expect.arrayContaining([
          'project_status_id',
          'business_function_id',
          'state_id',
          'product_category_id',
        ]),
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
  it('accepts a linked payload with the 4 classification fields left null (AC-020/AC-042)', () => {
    const schema = buildCreateCampaignSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(
      baseValues({
        project_id: 7,
        project_status_id: null,
        business_function_id: null,
        state_id: null,
        product_category_id: null,
      }),
    )
    expect(result.success).toBe(true)
  })
})
