import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { OperationalSiteDetailView } from '@/features/operational-sites/operational-site-detail'
import type { OperationalSiteDetail } from '@/features/operational-sites/types'

const BASE: OperationalSiteDetail = {
  id: 1,
  alias: 'Sede Milano',
  line1: 'Via Roma 1',
  postal_code: '20100',
  country_id: 1,
  country: { id: 1, name: 'Italy' },
  state_id: 2,
  region: { id: 2, name: 'Lombardy' },
  province_id: 3,
  province: { id: 3, name: 'Milano' },
  city_id: 4,
  city: { id: 4, name: 'Milan' },
  created_at: '2026-01-15T10:30:00Z',
}

describe('OperationalSiteDetailView', () => {
  it('renders the street (line1)', () => {
    render(<OperationalSiteDetailView operationalSite={BASE} />)
    expect(screen.getByText('Via Roma 1')).toBeInTheDocument()
  })

  it('renders the postal code', () => {
    render(<OperationalSiteDetailView operationalSite={BASE} />)
    expect(screen.getByText('20100')).toBeInTheDocument()
  })

  it('renders city, province, region and country names', () => {
    render(<OperationalSiteDetailView operationalSite={BASE} />)
    expect(screen.getByText('Milan')).toBeInTheDocument()
    expect(screen.getByText('Lombardy')).toBeInTheDocument()
    expect(screen.getByText('Italy')).toBeInTheDocument()
  })

  it('renders an em dash for a missing geo level', () => {
    render(<OperationalSiteDetailView operationalSite={{ ...BASE, country: null }} />)
    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })

  it('renders an em dash for a missing postal code', () => {
    render(<OperationalSiteDetailView operationalSite={{ ...BASE, postal_code: null }} />)
    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })

  it('renders the formatted creation date', () => {
    render(<OperationalSiteDetailView operationalSite={BASE} />)
    expect(screen.getByText(i18n.t('operationalSites.detail.line1'))).toBeInTheDocument()
    expect(screen.getByText(/2026/)).toBeInTheDocument()
  })
})
