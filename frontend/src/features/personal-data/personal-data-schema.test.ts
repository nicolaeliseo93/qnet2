import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildPersonalDataSchema } from '@/features/personal-data/personal-data-schema'

const t = ((key: string) => key) as unknown as TFunction
const schema = buildPersonalDataSchema(t)

describe('personal-data schema (per-type requirements)', () => {
  it('requires first and last name for an individual', () => {
    expect(schema.safeParse({ type: 'individual' }).success).toBe(false)
    expect(
      schema.safeParse({
        type: 'individual',
        first_name: 'Ada',
        last_name: 'Lovelace',
      }).success,
    ).toBe(true)
  })

  it('requires a company name for a company', () => {
    expect(schema.safeParse({ type: 'company' }).success).toBe(false)
    expect(
      schema.safeParse({ type: 'company', company_name: 'Engines Ltd' }).success,
    ).toBe(true)
  })

  it('rejects a future birth date', () => {
    const tomorrow = new Date(Date.now() + 86_400_000).toISOString().slice(0, 10)
    expect(
      schema.safeParse({
        type: 'individual',
        first_name: 'Ada',
        last_name: 'Lovelace',
        birth_date: tomorrow,
      }).success,
    ).toBe(false)
  })
})
