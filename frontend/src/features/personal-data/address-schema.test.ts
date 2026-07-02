import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildAddressSchema } from '@/features/personal-data/address-schema'

const t = ((key: string) => key) as unknown as TFunction
const schema = buildAddressSchema(t)

function parse(overrides: Record<string, unknown>) {
  return schema.safeParse({
    line1: '10 Downing Street',
    is_primary: false,
    ...overrides,
  })
}

describe('address schema', () => {
  it('requires line1', () => {
    expect(parse({ line1: '' }).success).toBe(false)
    expect(parse({}).success).toBe(true)
  })

  it('no longer accepts latitude/longitude as part of the shape', () => {
    const result = parse({ latitude: '51.5', longitude: '-0.12' })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data).not.toHaveProperty('latitude')
      expect(result.data).not.toHaveProperty('longitude')
    }
  })

  it('accepts nullable geo ids', () => {
    expect(
      parse({
        country_id: null,
        state_id: null,
        province_id: null,
        city_id: null,
      }).success,
    ).toBe(true)
    expect(
      parse({ country_id: 1, state_id: 2, province_id: 4, city_id: 3 }).success,
    ).toBe(true)
    expect(parse({ province_id: 'x' }).success).toBe(false)
    expect(parse({ country_id: 'x' }).success).toBe(false)
  })

  it('requires is_primary to be a boolean', () => {
    expect(parse({ is_primary: true }).success).toBe(true)
    expect(
      schema.safeParse({ line1: '10 Downing Street' }).success,
    ).toBe(false)
  })
})
