import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildContactSchema } from '@/features/personal-data/contact-schema'

// The schema only uses `t` to build messages; tests assert on success/failure.
const t = ((key: string) => key) as unknown as TFunction
const schema = buildContactSchema(t)

function parse(overrides: Record<string, unknown>) {
  return schema.safeParse({
    type: 'email',
    value: 'ada@example.com',
    label: '',
    is_primary: false,
    ...overrides,
  })
}

describe('contact schema (per-type value rules)', () => {
  it('accepts a valid email for an email/pec contact', () => {
    expect(parse({ type: 'email', value: 'ada@example.com' }).success).toBe(true)
    expect(parse({ type: 'pec', value: 'office@pec.example.com' }).success).toBe(
      true,
    )
  })

  it('rejects an invalid email for an email contact', () => {
    expect(parse({ type: 'email', value: 'not-an-email' }).success).toBe(false)
  })

  it('accepts a url for a website and rejects a non-url', () => {
    expect(parse({ type: 'website', value: 'https://example.com' }).success).toBe(
      true,
    )
    expect(parse({ type: 'website', value: 'definitely not a url' }).success).toBe(
      false,
    )
  })

  it('accepts a phone-shaped value and rejects letters', () => {
    expect(parse({ type: 'phone', value: '+39 333 123 4567' }).success).toBe(true)
    expect(parse({ type: 'phone', value: 'call-me' }).success).toBe(false)
  })

  it('requires a non-empty value and a type', () => {
    expect(parse({ value: '' }).success).toBe(false)
    expect(parse({ type: '' }).success).toBe(false)
  })
})
