import { describe, expect, it } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useEntityDetail } from '@/hooks/use-entity-detail'

interface Detail {
  id: number
  name: string
}

function makeWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return { client, wrapper }
}

/** A promise whose resolution is controlled by the test. */
function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason?: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

describe('useEntityDetail', () => {
  it('shows the loading state until the first fetch settles, then exposes data', async () => {
    const { wrapper } = makeWrapper()
    const fresh: Detail = { id: 1, name: 'fresh' }

    const { result } = renderHook(
      () =>
        useEntityDetail<Detail>(['users', 'detail', 1], () =>
          Promise.resolve(fresh),
        ),
      { wrapper },
    )

    expect(result.current.isLoading).toBe(true)
    expect(result.current.data).toBeUndefined()

    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(fresh)
    expect(result.current.isError).toBe(false)
  })

  it('refetches on open and keeps loading until fresh data replaces the stale cache', async () => {
    const { client, wrapper } = makeWrapper()
    const stale: Detail = { id: 1, name: 'stale' }
    const fresh: Detail = { id: 1, name: 'fresh' }
    const key = ['users', 'detail', 1] as const

    // Simulate a previous open (e.g. the view sheet) that left a stale snapshot.
    client.setQueryData(key, stale)

    const gate = deferred<Detail>()
    const { result } = renderHook(
      () => useEntityDetail<Detail>(key, () => gate.promise),
      { wrapper },
    )

    // The cache holds `stale`, but because we refetch on open the hook must NOT
    // report ready — otherwise an edit form would initialize from stale values.
    expect(result.current.isLoading).toBe(true)

    gate.resolve(fresh)

    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(fresh)
  })

  it('surfaces the error state with a working refetch', async () => {
    const { wrapper } = makeWrapper()
    let attempt = 0
    const fresh: Detail = { id: 1, name: 'fresh' }

    const { result } = renderHook(
      () =>
        useEntityDetail<Detail>(['users', 'detail', 1], () => {
          attempt += 1
          return attempt === 1
            ? Promise.reject(new Error('boom'))
            : Promise.resolve(fresh)
        }),
      { wrapper },
    )

    await waitFor(() => expect(result.current.isError).toBe(true))

    void result.current.refetch()

    await waitFor(() => expect(result.current.data).toEqual(fresh))
    expect(result.current.isError).toBe(false)
  })
})
