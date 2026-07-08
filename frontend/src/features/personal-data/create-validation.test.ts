import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import {
  areCreateContactsValid,
  isCreateAddressValid,
} from '@/features/personal-data/create-validation'
import type { AddressDraft, ContactDraft } from '@/features/personal-data/types'

// The contact schema only uses `t` to build messages; these tests assert on
// success/failure, mirroring `contact-schema.test.ts`.
const t = ((key: string) => key) as unknown as TFunction

function contact(overrides: Partial<ContactDraft> = {}): ContactDraft {
  return {
    _key: 'draft-1',
    type: 'email',
    value: 'ada@example.com',
    label: null,
    is_primary: true,
    ...overrides,
  }
}

function address(overrides: Partial<AddressDraft> = {}): AddressDraft {
  return {
    _key: 'draft-1',
    line1: 'Via Roma 1',
    line2: null,
    postal_code: null,
    city_id: 7,
    province_id: null,
    state_id: null,
    country_id: null,
    is_primary: true,
    site_type: 'billing',
    ...overrides,
  }
}

describe('isCreateAddressValid', () => {
  it('is valid when the buffer is empty (optional)', () => {
    expect(isCreateAddressValid([])).toBe(true)
  })

  it('is valid once line1 and the city are both set', () => {
    expect(isCreateAddressValid([address()])).toBe(true)
  })

  it('is invalid when line1 is missing', () => {
    expect(isCreateAddressValid([address({ line1: '' })])).toBe(false)
  })

  it('is invalid when the city is missing', () => {
    expect(isCreateAddressValid([address({ city_id: null })])).toBe(false)
  })
})

describe('areCreateContactsValid', () => {
  it('is valid when the buffer is empty', () => {
    expect(areCreateContactsValid([], t)).toBe(true)
  })

  it('is valid when every contact matches its per-type shape', () => {
    expect(
      areCreateContactsValid(
        [
          contact({ type: 'email', value: 'ada@example.com' }),
          contact({ _key: 'draft-2', type: 'phone', value: '+39 02 1234567' }),
        ],
        t,
      ),
    ).toBe(true)
  })

  it('is invalid when a contact does not match its type shape', () => {
    expect(
      areCreateContactsValid([contact({ type: 'email', value: 'not-an-email' })], t),
    ).toBe(false)
  })
})
