import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { LeadDetailView } from '@/features/leads/lead-detail'
import type { LeadDetailWithPermissions } from '@/features/leads/types'

/** AC-065: `/leads/:id` shows the 6 fields read-only via `DetailPanel`, no editable control. */

function lead(overrides: Partial<LeadDetailWithPermissions> = {}): LeadDetailWithPermissions {
  return {
    id: 1,
    registry_id: 10,
    registry: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status: 'associated',
    operational_site_id: 30,
    operational_site: { id: 30, label: 'Via Roma 1 - Milano' },
    source_id: 40,
    source: { id: 40, name: 'Web' },
    operator_id: 50,
    operator: { id: 50, name: 'Anna Bianchi' },
    notes: 'Interested in the enterprise plan.',
    extra_fields: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
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
    expect(screen.getByText('Associated')).toBeInTheDocument()
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

  it('falls back to a placeholder heading when the registry relation is missing', () => {
    render(<LeadDetailView lead={lead({ registry_id: 10, registry: null })} />)

    expect(screen.getByRole('heading', { name: 'Unknown registry' })).toBeInTheDocument()
  })
})

/** AC-014: the "Imported data" section shows extra_fields read-only, only when non-empty. */
describe('LeadDetailView — imported data (AC-014)', () => {
  it('shows the section with every key/value pair when extra_fields is set', () => {
    render(
      <LeadDetailView
        lead={lead({ extra_fields: { 'Original column A': 'foo', 'Original column B': 'bar' } })}
      />,
    )

    expect(screen.getByText('Imported data')).toBeInTheDocument()
    expect(screen.getByText('Original column A')).toBeInTheDocument()
    expect(screen.getByText('foo')).toBeInTheDocument()
    expect(screen.getByText('Original column B')).toBeInTheDocument()
    expect(screen.getByText('bar')).toBeInTheDocument()
  })

  it('does not render the section when extra_fields is null', () => {
    render(<LeadDetailView lead={lead({ extra_fields: null })} />)

    expect(screen.queryByText('Imported data')).not.toBeInTheDocument()
  })

  it('does not render the section when extra_fields is an empty object', () => {
    render(<LeadDetailView lead={lead({ extra_fields: {} })} />)

    expect(screen.queryByText('Imported data')).not.toBeInTheDocument()
  })
})
