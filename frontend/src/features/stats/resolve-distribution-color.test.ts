import { describe, expect, it } from 'vitest'
import { resolveDistributionColor } from '@/features/stats/resolve-distribution-color'

/**
 * Backend erratum (spec 0026): a distribution item's `color` is a color
 * TOKEN read from a lookup table ("teal", "slate", "amber"), not a literal
 * CSS color. `slate`/`amber` are not valid standalone CSS keywords, and the
 * value is DB-controlled content, so it must resolve through an allow-list.
 */
describe('resolveDistributionColor', () => {
  it('resolves a token that also happens to be a valid CSS keyword', () => {
    expect(resolveDistributionColor('teal')).toBe('var(--color-teal-500)')
  })

  it('resolves a token that is NOT a valid standalone CSS color', () => {
    expect(resolveDistributionColor('slate')).toBe('var(--color-slate-500)')
    expect(resolveDistributionColor('amber')).toBe('var(--color-amber-500)')
  })

  it('falls back to null for null', () => {
    expect(resolveDistributionColor(null)).toBeNull()
  })

  it('falls back to null for an unrecognized/garbage token, never injecting it raw', () => {
    expect(resolveDistributionColor('not-a-real-token')).toBeNull()
    expect(resolveDistributionColor('red; } body { display:none')).toBeNull()
  })

  it('falls back to null for an inherited Object.prototype key (allow-list hardening)', () => {
    expect(resolveDistributionColor('constructor')).toBeNull()
    expect(resolveDistributionColor('toString')).toBeNull()
    expect(resolveDistributionColor('hasOwnProperty')).toBeNull()
  })
})
