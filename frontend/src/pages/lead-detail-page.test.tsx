import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import LeadDetailPage from '@/pages/lead-detail-page'
import type { LeadDetail } from '@/features/leads/types'

/**
 * AC-078: the Lead detail page offers "Create opportunity" (gated
 * `opportunities.create`) navigating to `/opportunities/new?lead_id=N`, or
 * "Go to opportunity" when `lead.opportunity` is already set — mirrors
 * `registry-detail-page.test.tsx`'s page-wiring pattern. `LeadDetailView`
 * itself is stubbed: its own suite covers it.
 */

const fetchLeadMock = vi.fn<(id: number) => Promise<LeadDetail>>()

vi.mock('@/features/leads/api', () => ({
  fetchLead: (id: number) => fetchLeadMock(id),
  leadDetailQueryKey: (id: number | null) => ['leads', 'detail', id] as const,
}))

vi.mock('@/features/leads/lead-detail', () => ({
  LeadDetailView: ({ lead }: { lead: LeadDetail }) => <h2>{lead.registry?.name}</h2>,
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const canMock = vi.fn()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

function lead(overrides: Partial<LeadDetail> = {}): LeadDetail {
  return {
    id: 9,
    registry_id: 10,
    registry: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status_id: 30,
    lead_status: { id: 30, name: 'New', color: 'slate' },
    operational_site_id: null,
    operational_site: null,
    source_id: null,
    source: null,
    operator_id: null,
    operator: null,
    notes: null,
    extra_fields: null,
    opportunity: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  } as LeadDetail
}

function renderAt(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/leads/:id" element={<LeadDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchLeadMock.mockReset()
  canMock.mockReset()
})

describe('LeadDetailPage — opportunity action (AC-078)', () => {
  it('shows "Create opportunity" navigating to /opportunities/new?lead_id=N when granted and no opportunity exists', async () => {
    canMock.mockReturnValue(true)
    fetchLeadMock.mockResolvedValue(lead({ opportunity: null }))

    renderAt('/leads/9')

    const link = await screen.findByRole('link', { name: 'Create opportunity' })
    expect(link).toHaveAttribute('href', '/opportunities/new?lead_id=9')
    expect(screen.queryByRole('link', { name: 'Go to opportunity' })).not.toBeInTheDocument()
  })

  it('hides "Create opportunity" without opportunities.create', async () => {
    canMock.mockReturnValue(false)
    fetchLeadMock.mockResolvedValue(lead({ opportunity: null }))

    renderAt('/leads/9')

    await screen.findByRole('heading', { name: 'Mario Rossi' })
    expect(screen.queryByRole('link', { name: 'Create opportunity' })).not.toBeInTheDocument()
  })

  it('shows "Go to opportunity" instead, when the lead already has one', async () => {
    canMock.mockReturnValue(true)
    fetchLeadMock.mockResolvedValue(lead({ opportunity: { id: 77, name: 'Enterprise deal' } }))

    renderAt('/leads/9')

    const link = await screen.findByRole('link', { name: 'Go to opportunity' })
    expect(link).toHaveAttribute('href', '/opportunities/77')
    expect(screen.queryByRole('link', { name: 'Create opportunity' })).not.toBeInTheDocument()
  })
})
