import { describe, expect, it } from 'vitest'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/** Spec 0021 AC-023: create=all valued fields, update=sparse diff only. */

describe('buildCustomFieldsCreate', () => {
  it('includes every set value', () => {
    expect(
      buildCustomFieldsCreate({ notes: 'hello', age: 30, active: true, tiers: ['gold', 'silver'] }),
    ).toEqual({ notes: 'hello', age: 30, active: true, tiers: ['gold', 'silver'] })
  })

  it('omits null/empty-string/empty-array values', () => {
    expect(
      buildCustomFieldsCreate({ notes: '', owner: null, tiers: [], age: 0 }),
    ).toEqual({ age: 0 })
  })

  it('keeps an explicit false (not "empty")', () => {
    expect(buildCustomFieldsCreate({ active: false })).toEqual({ active: false })
  })
})

describe('buildCustomFieldsUpdate', () => {
  it('returns only the keys that changed from the original', () => {
    const original = { notes: 'old', age: 30, active: true }
    const values = { notes: 'new', age: 30, active: true }
    expect(buildCustomFieldsUpdate(values, original)).toEqual({ notes: 'new' })
  })

  it('returns an empty object when nothing changed', () => {
    const original = { notes: 'same', tiers: ['gold', 'silver'] }
    const values = { notes: 'same', tiers: ['gold', 'silver'] }
    expect(buildCustomFieldsUpdate(values, original)).toEqual({})
  })

  it('treats a re-ordered array as unchanged', () => {
    const original = { tiers: ['gold', 'silver'] }
    const values = { tiers: ['silver', 'gold'] }
    expect(buildCustomFieldsUpdate(values, original)).toEqual({})
  })

  it('includes a key set for the first time (absent from the original)', () => {
    const original = {}
    const values = { notes: 'first value' }
    expect(buildCustomFieldsUpdate(values, original)).toEqual({ notes: 'first value' })
  })

  it('includes a key cleared back to empty', () => {
    const original = { notes: 'old' }
    const values = { notes: '' }
    expect(buildCustomFieldsUpdate(values, original)).toEqual({ notes: '' })
  })
})
