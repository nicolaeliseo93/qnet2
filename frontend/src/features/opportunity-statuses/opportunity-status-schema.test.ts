import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateOpportunityStatusSchema,
  buildUpdateOpportunityStatusSchema,
} from '@/features/opportunity-statuses/opportunity-status-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateOpportunityStatusSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateOpportunityStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Trattativa', color: 'blue', group: 'open' })
    expect(result.success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateOpportunityStatusSchema(i18n.t)
    const result = schema.safeParse({ name: '', color: '', group: 'open' })
    expect(result.success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateOpportunityStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'a'.repeat(192), color: '', group: 'open' })
    expect(result.success).toBe(false)
  })

  it('accepts an empty color (unset)', () => {
    const schema = buildCreateOpportunityStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Trattativa', color: '', group: 'open' })
    expect(result.success).toBe(true)
  })

  it.each(['open', 'pending', 'closed'] as const)('accepts the group value "%s"', (group) => {
    const schema = buildCreateOpportunityStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Trattativa', color: '', group })
    expect(result.success).toBe(true)
  })

  it('rejects a group value outside the fixed enum', () => {
    const schema = buildCreateOpportunityStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Trattativa', color: '', group: 'archived' })
    expect(result.success).toBe(false)
  })

  it('rejects a missing group (required on create)', () => {
    const schema = buildCreateOpportunityStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Trattativa', color: '' })
    expect(result.success).toBe(false)
  })
})

describe('buildUpdateOpportunityStatusSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateOpportunityStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Persa', color: 'red', group: 'closed' })
    expect(result.success).toBe(true)
  })
})
