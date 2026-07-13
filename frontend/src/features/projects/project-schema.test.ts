import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCreateProjectSchema } from '@/features/projects/project-schema'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'

/** Spec 0023 D-5 (required status) and BR-6 (end_date >= start_date), mirrored client-side. */

const EMPTY_CUSTOM_FIELDS_SCHEMA = buildCustomFieldsSchema(
  [],
  { resource: { view: true, create: true, update: true, delete: true, export: true, import: true }, fields: {}, actions: {} },
  i18n.t,
)

function baseValues() {
  return {
    name: 'New project',
    description: null,
    registry_id: null,
    project_status_id: 1,
    source_id: null,
    business_function_id: null,
    state_id: null,
    product_category_id: null,
    partner_id: null,
    start_date: '',
    end_date: '',
    total_budget: null,
    target_lead: null,
    custom_fields: {},
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateProjectSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  it('rejects a missing name', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), name: '' })
    expect(result.success).toBe(false)
  })

  it('rejects a null project_status_id (D-5)', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), project_status_id: null })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'project_status_id')).toBe(true)
    }
  })

  it('rejects end_date earlier than start_date (BR-6)', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({
      ...baseValues(),
      start_date: '2026-02-01',
      end_date: '2026-01-01',
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'end_date')).toBe(true)
    }
  })

  it('accepts end_date equal to start_date', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({
      ...baseValues(),
      start_date: '2026-02-01',
      end_date: '2026-02-01',
    })
    expect(result.success).toBe(true)
  })

  it('accepts an end_date with no start_date set', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), end_date: '2026-02-01' })
    expect(result.success).toBe(true)
  })
})
