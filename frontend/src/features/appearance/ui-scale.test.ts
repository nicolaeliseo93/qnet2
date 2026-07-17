import { describe, expect, it } from 'vitest'
import {
  clampScale,
  scaleToFactor,
  scaleToPercent,
  UI_SCALE_DEFAULT,
} from '@/features/appearance/ui-scale'

describe('ui-scale mapping', () => {
  it('maps the 0..100 slider onto the 80..130% band', () => {
    expect(scaleToPercent(0)).toBe(80)
    expect(scaleToPercent(UI_SCALE_DEFAULT)).toBe(100)
    expect(scaleToPercent(100)).toBe(130)
  })

  it('is linear between the bounds', () => {
    expect(scaleToPercent(20)).toBe(90)
    expect(scaleToPercent(60)).toBe(110)
    expect(scaleToPercent(80)).toBe(120)
  })

  it('derives the factor as percent / 100', () => {
    expect(scaleToFactor(0)).toBeCloseTo(0.8)
    expect(scaleToFactor(UI_SCALE_DEFAULT)).toBeCloseTo(1)
    expect(scaleToFactor(100)).toBeCloseTo(1.3)
  })

  it('clamps out-of-range, rounds fractional, and defaults non-finite input', () => {
    expect(clampScale(-10)).toBe(0)
    expect(clampScale(150)).toBe(100)
    expect(clampScale(42.6)).toBe(43)
    expect(clampScale(Number.NaN)).toBe(UI_SCALE_DEFAULT)
  })
})
