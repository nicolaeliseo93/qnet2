import { describe, expect, it, vi, beforeEach } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import {
  flattenForSelectPages,
  useForSelect,
  useForSelectLabels,
} from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  PaginatedResponse,
} from '@/features/for-select/types'

const fetchForSelectMock = vi.fn()

vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (resource: string, params: unknown) =>
    fetchForSelectMock(resource, params),
}))

function page(
  items: ForSelectItem[],
  pagination: { total: number; offset: number; limit: number },
): PaginatedResponse<ForSelectItem> {
  return {
    items,
    export_link: null,
    pagination: { ...pagination, total_pages: 1 },
  }
}

function wrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  fetchForSelectMock.mockReset()
})

describe('useForSelect', () => {
  it('requests the first page with offset 0 and hydration ids', async () => {
    fetchForSelectMock.mockResolvedValue(
      page([{ id: 1, label: 'Jane' }], { total: 1, offset: 0, limit: 25 }),
    )

    const { result } = renderHook(
      () => useForSelect({ resource: 'users', search: 'ja', ids: [7] }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(fetchForSelectMock).toHaveBeenCalledWith('users', {
      search: 'ja',
      offset: 0,
      limit: 25,
      ids: [7],
    })
  })

  it('stops paginating once offset + limit reaches total', async () => {
    fetchForSelectMock.mockResolvedValue(
      page([{ id: 1, label: 'A' }], { total: 1, offset: 0, limit: 25 }),
    )

    const { result } = renderHook(
      () => useForSelect({ resource: 'users', search: '' }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.hasNextPage).toBe(false)
  })

  it('exposes a next page while more rows remain and drops ids on later pages', async () => {
    fetchForSelectMock
      .mockResolvedValueOnce(
        page([{ id: 1, label: 'A' }], { total: 30, offset: 0, limit: 25 }),
      )
      .mockResolvedValueOnce(
        page([{ id: 2, label: 'B' }], { total: 30, offset: 25, limit: 25 }),
      )

    const { result } = renderHook(
      () => useForSelect({ resource: 'users', search: '', ids: [99] }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.hasNextPage).toBe(true)

    await result.current.fetchNextPage()
    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledTimes(2),
    )

    // First page carries ids; the second page must not re-send them.
    expect(fetchForSelectMock.mock.calls[0][1]).toMatchObject({ ids: [99] })
    expect(fetchForSelectMock.mock.calls[1][1]).toMatchObject({
      offset: 25,
      ids: undefined,
    })
  })

  it('does not run while disabled', () => {
    renderHook(
      () => useForSelect({ resource: 'users', search: '', enabled: false }),
      { wrapper: wrapper() },
    )
    expect(fetchForSelectMock).not.toHaveBeenCalled()
  })
})

describe('useForSelectLabels', () => {
  it('resolves an id set into a label map, requesting exactly those ids', async () => {
    fetchForSelectMock.mockResolvedValue(
      page(
        [
          { id: 5, label: 'Alice' },
          { id: 88, label: 'Bob' },
        ],
        { total: 0, offset: 0, limit: 2 },
      ),
    )

    const { result } = renderHook(
      () => useForSelectLabels({ resource: 'users', ids: [88, 5] }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.size).toBe(2))
    expect(result.current.get(5)?.label).toBe('Alice')
    expect(result.current.get(88)?.label).toBe('Bob')
    // Ids are sorted for a stable, order-independent query key.
    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({ offset: 0, ids: [5, 88] }),
    )
  })

  it('does not run for an empty id set', () => {
    const { result } = renderHook(
      () => useForSelectLabels({ resource: 'users', ids: [] }),
      { wrapper: wrapper() },
    )
    expect(fetchForSelectMock).not.toHaveBeenCalled()
    expect(result.current.size).toBe(0)
  })

  it('does not run while disabled', () => {
    renderHook(
      () => useForSelectLabels({ resource: 'users', ids: [5], enabled: false }),
      { wrapper: wrapper() },
    )
    expect(fetchForSelectMock).not.toHaveBeenCalled()
  })
})

describe('flattenForSelectPages', () => {
  it('returns an empty list when there are no pages', () => {
    expect(flattenForSelectPages(undefined)).toEqual([])
  })

  it('flattens pages and de-duplicates by id', () => {
    const result = flattenForSelectPages([
      { items: [{ id: 1, label: 'A' }, { id: 2, label: 'B' }] },
      { items: [{ id: 2, label: 'B' }, { id: 3, label: 'C' }] },
    ])
    expect(result.map((item) => item.id)).toEqual([1, 2, 3])
  })
})
