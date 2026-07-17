import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { LeadDetailPageActions } from '@/features/leads/lead-screens'
import type { LeadDetail } from '@/features/leads/types'

/**
 * Recovers the coverage of the deleted `pages/lead-detail-page.test.tsx`
 * (AC-078): the lead detail dedicated page offers "Create opportunity"
 * (gated `opportunities.create`) navigating to `/opportunities/new?lead_id=N`,
 * or "Go to opportunity" when `lead.opportunity` is set. That logic now lives
 * in `LeadDetailPageActions`, rendered by the generic `ModuleDetailPage`.
 */

const fetchLeadMock = vi.fn<(id: number) => Promise<LeadDetail>>()

vi.mock('@/features/leads/api', () => ({
  fetchLead: (id: number) => fetchLeadMock(id),
  leadDetailQueryKey: (id: number | null) => ['leads', 'detail', id] as const,
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

function lead(overrides: Partial<LeadDetail> = {}): LeadDetail {
  return { id: 9, opportunity: null, ...overrides } as LeadDetail
}

function renderActions() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <LeadDetailPageActions id={9} />
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
  canMock.mockReturnValue(true)
})

describe('LeadDetailPageActions', () => {
  it('AC-078: offers "Create opportunity" to /opportunities/new?lead_id=N when the lead has none', async () => {
    fetchLeadMock.mockResolvedValue(lead({ opportunity: null }))

    renderActions()

    const link = await screen.findByRole('link')
    expect(link).toHaveAttribute('href', '/opportunities/new?lead_id=9')
  })

  it('AC-078: offers "Go to opportunity" to the existing opportunity when the lead has one', async () => {
    fetchLeadMock.mockResolvedValue(
      lead({ opportunity: { id: 42 } as LeadDetail['opportunity'] }),
    )

    renderActions()

    const link = await screen.findByRole('link')
    expect(link).toHaveAttribute('href', '/opportunities/42')
  })

  it('AC-078: hides the "Create opportunity" action without the opportunities.create permission', async () => {
    canMock.mockReturnValue(false)
    fetchLeadMock.mockResolvedValue(lead({ opportunity: null }))

    renderActions()

    await waitFor(() => expect(fetchLeadMock).toHaveBeenCalled())
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })
})
