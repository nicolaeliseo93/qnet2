import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { GeoScopeBadge } from '@/features/geo/geo-scope-badge'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('GeoScopeBadge', () => {
  it('renders the scope label and the place name', () => {
    render(<GeoScopeBadge scope="city" place="Milan" />)

    expect(screen.getByText('City')).toBeInTheDocument()
    expect(screen.getByText('Milan')).toBeInTheDocument()
  })

  it('renders the national label for a country scope', () => {
    render(<GeoScopeBadge scope="country" place="Italy" />)

    expect(screen.getByText('National')).toBeInTheDocument()
    expect(screen.getByText('Italy')).toBeInTheDocument()
  })

  it('renders only the scope label when no place is given (e.g. the projects table column)', () => {
    render(<GeoScopeBadge scope="province" />)

    expect(screen.getByText('Provincial')).toBeInTheDocument()
  })
})
