import { describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useOpportunityDefaults } from '@/features/opportunities/use-opportunity-defaults'
import type { OpportunityDefaults } from '@/features/opportunities/types'

/** AC-075: the create-from-lead defaults query is gated by a valid `lead_id`, never fired for a plain manual create. */

const fetchOpportunityDefaultsMock = vi.fn<() => Promise<OpportunityDefaults>>()
vi.mock('@/features/opportunities/opportunity-defaults-api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/opportunity-defaults-api')>(
    '@/features/opportunities/opportunity-defaults-api',
  )
  return {
    ...actual,
    fetchOpportunityDefaults: () => fetchOpportunityDefaultsMock(),
  }
})

function defaults(overrides: Partial<OpportunityDefaults> = {}): OpportunityDefaults {
  return {
    lead_id: 9,
    existing_opportunity_id: null,
    values: {
      referent_id: 10,
      source_id: 20,
      operational_site_id: null,
      registry_id: 30,
      business_function_id: 40,
      product_category_id: 50,
    },
    references: {
      referent: { id: 10, name: 'Mario Rossi' },
      source: { id: 20, name: 'Web' },
      operational_site: null,
      registry: { id: 30, name: 'Acme S.p.A.' },
      business_function: { id: 40, name: 'Sales' },
      product_category: { id: 50, name: 'Consulting' },
    },
    locked_fields: ['referent_id', 'source_id', 'registry_id', 'business_function_id', 'product_category_id'],
    ...overrides,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

describe('useOpportunityDefaults', () => {
  it('does not fetch when leadId is null', () => {
    const { result } = renderHook(() => useOpportunityDefaults(null), { wrapper: wrapper() })

    expect(fetchOpportunityDefaultsMock).not.toHaveBeenCalled()
    expect(result.current.data).toBeUndefined()
  })

  it('fetches and resolves the defaults for a given leadId', async () => {
    fetchOpportunityDefaultsMock.mockResolvedValue(defaults())

    const { result } = renderHook(() => useOpportunityDefaults(9), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.data).toBeDefined())
    expect(result.current.data?.values.registry_id).toBe(30)
    expect(result.current.data?.locked_fields).toContain('registry_id')
    expect(fetchOpportunityDefaultsMock).toHaveBeenCalledTimes(1)
  })

  it('surfaces existing_opportunity_id when the lead is already linked (D-2)', async () => {
    fetchOpportunityDefaultsMock.mockResolvedValue(defaults({ existing_opportunity_id: 77 }))

    const { result } = renderHook(() => useOpportunityDefaults(9), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.data?.existing_opportunity_id).toBe(77))
  })
})
