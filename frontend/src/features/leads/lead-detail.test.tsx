import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { LeadDetailView } from '@/features/leads/lead-detail'
import type { LeadDetail } from '@/features/leads/types'

/** AC-065: `/leads/:id` shows the 6 fields read-only via `DetailPanel`, no editable control. */

function lead(overrides: Partial<LeadDetail> = {}): LeadDetail {
  return {
    id: 1,
    referent_id: 10,
    referent: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status_id: 60,
    lead_status: { id: 60, name: 'Qualified', color: 'green' },
    operational_site_id: 30,
    operational_site: { id: 30, label: 'Via Roma 1 - Milano' },
    source_id: 40,
    source: { id: 40, name: 'Web' },
    operator_id: 50,
    operator: { id: 50, name: 'Anna Bianchi' },
    notes: 'Interested in the enterprise plan.',
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('LeadDetailView — read-only (AC-065)', () => {
  it('renders the 7 fields as read-only text, including the colored lead status badge (AC-015)', () => {
    render(<LeadDetailView lead={lead()} />)

    expect(screen.getByRole('heading', { name: 'Mario Rossi' })).toBeInTheDocument()
    expect(screen.getByText('CMP-0001 — Spring push')).toBeInTheDocument()
    expect(screen.getByText('Via Roma 1 - Milano')).toBeInTheDocument()
    expect(screen.getByText('Web')).toBeInTheDocument()
    expect(screen.getByText('Qualified')).toBeInTheDocument()
    expect(screen.getByText('Anna Bianchi')).toBeInTheDocument()
    expect(screen.getByText('Interested in the enterprise plan.')).toBeInTheDocument()
  })

  it('renders no editable control', () => {
    render(<LeadDetailView lead={lead()} />)

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('shows an em dash placeholder for unset optional fields', () => {
    render(
      <LeadDetailView
        lead={lead({
          operational_site_id: null,
          operational_site: null,
          source_id: null,
          source: null,
          operator_id: null,
          operator: null,
          notes: null,
        })}
      />,
    )

    expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(4)
  })

  it('falls back to a placeholder heading when the referent relation is missing', () => {
    render(<LeadDetailView lead={lead({ referent_id: 10, referent: null })} />)

    expect(screen.getByRole('heading', { name: 'Unknown contact' })).toBeInTheDocument()
  })
})
