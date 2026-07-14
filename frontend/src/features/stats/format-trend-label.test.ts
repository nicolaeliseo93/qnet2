import { describe, expect, it } from 'vitest'
import { formatTrendLabel } from '@/features/stats/format-trend-label'

describe('formatTrendLabel', () => {
  it('formats a YYYY-MM contract label into a short, locale-aware month', () => {
    expect(formatTrendLabel('2026-02', 'en')).toBe('Feb 2026')
  })

  it('localizes the same label for another locale', () => {
    expect(formatTrendLabel('2026-02', 'it')).toBe('feb 2026')
  })

  it('falls back to the raw label when it does not match YYYY-MM', () => {
    expect(formatTrendLabel('not-a-month', 'en')).toBe('not-a-month')
  })
})
