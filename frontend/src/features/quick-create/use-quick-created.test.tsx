import { describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useQuickCreated } from '@/features/quick-create/use-quick-created'
import { forSelectKeys } from '@/features/for-select/query-keys'

/** Spec 0028 AC-005/AC-006/AC-010 — accumulation + cache invalidation on create. */
describe('useQuickCreated', () => {
  function wrapper(client: QueryClient) {
    return function Wrapper({ children }: { children: ReactNode }) {
      return <QueryClientProvider client={client}>{children}</QueryClientProvider>
    }
  }

  it('accumulates every created ref instead of replacing it (AC-010 multi-select support)', () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const { result } = renderHook(() => useQuickCreated('sources'), { wrapper: wrapper(client) })

    act(() => result.current.handleCreated({ id: 1, name: 'Fiera' }))
    act(() => result.current.handleCreated({ id: 2, name: 'Referral' }))

    expect(result.current.quickCreated).toEqual([
      { id: 1, name: 'Fiera' },
      { id: 2, name: 'Referral' },
    ])
  })

  it('invalidates the resource for-select cache on every creation (AC-005)', () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')
    const { result } = renderHook(() => useQuickCreated('sources'), { wrapper: wrapper(client) })

    act(() => result.current.handleCreated({ id: 1, name: 'Fiera' }))

    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: forSelectKeys.resource('sources') })
  })
})
