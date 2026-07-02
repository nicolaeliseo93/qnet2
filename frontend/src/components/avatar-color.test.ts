import { describe, expect, it } from 'vitest'
import { avatarColor } from './avatar-color'

const HEX = /^#[0-9a-f]{6}$/i

describe('avatarColor', () => {
  it('returns the same color for the same name', () => {
    expect(avatarColor('Mario Rossi')).toEqual(avatarColor('Mario Rossi'))
  })

  it('ignores leading/trailing whitespace and case', () => {
    expect(avatarColor('  Mario Rossi  ')).toEqual(avatarColor('mario rossi'))
  })

  it('falls back to a valid color pair for empty or whitespace names', () => {
    for (const name of ['', '   ']) {
      const color = avatarColor(name)
      expect(color.bg).toMatch(HEX)
      expect(color.fg).toMatch(HEX)
    }
  })

  it('always returns a valid hex color pair', () => {
    for (const name of ['Anna', 'Luca Bianchi', 'Zoe', 'X Y Z']) {
      const color = avatarColor(name)
      expect(color.bg).toMatch(HEX)
      expect(color.fg).toMatch(HEX)
    }
  })

  it('distributes different names across more than one color', () => {
    const names = Array.from({ length: 20 }, (_, i) => `User ${i}`)
    const distinct = new Set(names.map((name) => avatarColor(name).bg))
    expect(distinct.size).toBeGreaterThan(1)
  })
})
