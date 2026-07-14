import { describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { moduleStatsQueryKey } from '@/features/stats/api'

function wrapper(client: QueryClient) {
  return function QueryWrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
}

describe('useInvalidateModuleStats', () => {
  it('marks the module stats query key stale, scoped to its own domain', () => {
    const client = new QueryClient()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    const { result } = renderHook(() => useInvalidateModuleStats('leads'), {
      wrapper: wrapper(client),
    })

    result.current()

    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: moduleStatsQueryKey('leads') })
  })

  it('returns a stable callback across re-renders', () => {
    const client = new QueryClient()

    const { result, rerender } = renderHook(() => useInvalidateModuleStats('leads'), {
      wrapper: wrapper(client),
    })
    const first = result.current

    rerender()

    expect(result.current).toBe(first)
  })
})
