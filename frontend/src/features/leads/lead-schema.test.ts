import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCreateLeadSchema } from '@/features/leads/lead-schema'

/** AC-060: contact and campaign missing produce a localized validation error; the other 4 fields empty pass. */

function baseValues(overrides: Record<string, unknown> = {}) {
  return {
    referent_id: 1,
    campaign_id: 2,
    operational_site_id: null,
    source_id: null,
    operator_id: null,
    notes: null,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateLeadSchema', () => {
  it('accepts a valid payload with only the required contact and campaign set', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  it('rejects a missing referent_id (contact)', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ referent_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'referent_id')).toBe(true)
    }
  })

  it('rejects a missing campaign_id', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ campaign_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'campaign_id')).toBe(true)
    }
  })

  it('accepts the 4 optional fields left null', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(
      baseValues({ operational_site_id: null, source_id: null, operator_id: null, notes: null }),
    )
    expect(result.success).toBe(true)
  })

  it('accepts the 4 optional fields when set', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(
      baseValues({ operational_site_id: 3, source_id: 4, operator_id: 5, notes: 'Some note' }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects notes beyond the 5000-character limit', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ notes: 'a'.repeat(5001) }))
    expect(result.success).toBe(false)
  })
})
