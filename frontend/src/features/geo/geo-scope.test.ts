import { describe, expect, it } from 'vitest'
import { geoScopeLabelKey, geoScopePlaceName } from '@/features/geo/geo-scope'

describe('geoScopeLabelKey', () => {
  it('maps every scope level to its own i18n key (spec 0027 D-2)', () => {
    expect(geoScopeLabelKey('country')).toBe('geo.scope.country')
    expect(geoScopeLabelKey('state')).toBe('geo.scope.state')
    expect(geoScopeLabelKey('province')).toBe('geo.scope.province')
    expect(geoScopeLabelKey('city')).toBe('geo.scope.city')
  })
})

describe('geoScopePlaceName', () => {
  const names = {
    country: { name: 'Italia' },
    state: { name: 'Lombardia' },
    province: { name: 'Milano' },
    city: { name: 'Milano (comune)' },
  }

  it('picks the city name for a city scope', () => {
    expect(geoScopePlaceName('city', names)).toBe('Milano (comune)')
  })

  it('picks the province name for a province scope', () => {
    expect(geoScopePlaceName('province', names)).toBe('Milano')
  })

  it('picks the state name for a state scope', () => {
    expect(geoScopePlaceName('state', names)).toBe('Lombardia')
  })

  it('picks the country name for a country scope', () => {
    expect(geoScopePlaceName('country', names)).toBe('Italia')
  })

  it('returns null when the matching level ref is missing', () => {
    expect(
      geoScopePlaceName('city', { country: null, state: null, province: null, city: null }),
    ).toBeNull()
  })
})
