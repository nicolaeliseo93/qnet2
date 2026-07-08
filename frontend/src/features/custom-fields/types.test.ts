import { describe, expect, it } from 'vitest'
import { isCustomFieldDescriptor, namespacedKey, rawKey } from '@/features/custom-fields/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import type { FieldDescriptor } from '@/features/authorization/types'

describe('rawKey / namespacedKey', () => {
  it('strips the custom. namespace off a meta key', () => {
    expect(rawKey('custom.headcount')).toBe('headcount')
  })

  it('leaves an already-raw key untouched', () => {
    expect(rawKey('headcount')).toBe('headcount')
  })

  it('re-applies the namespace to a raw key', () => {
    expect(namespacedKey('headcount')).toBe('custom.headcount')
  })

  it('round-trips', () => {
    expect(rawKey(namespacedKey('vat_number'))).toBe('vat_number')
  })
})

describe('isCustomFieldDescriptor', () => {
  const custom: CustomFieldDescriptor = {
    key: 'custom.notes',
    type: 'text',
    label: 'Notes',
    group: null,
    mandatory: false,
    source: 'custom',
  }

  const native: FieldDescriptor = {
    key: 'name',
    type: 'text',
    group: null,
    mandatory: true,
  }

  it('recognizes a source:custom descriptor', () => {
    expect(isCustomFieldDescriptor(custom)).toBe(true)
  })

  it('rejects a native descriptor with no source', () => {
    expect(isCustomFieldDescriptor(native)).toBe(false)
  })
})
