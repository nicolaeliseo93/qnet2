import { describe, expect, it, vi, beforeEach } from 'vitest'
import { fetchForSelect } from '@/features/for-select/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn() },
}))

const getMock = vi.mocked(apiClient.get)

/**
 * Paginated list endpoints return the body directly (no `{ success, message,
 * data }` envelope), so the axios response `data` IS the paginated body.
 */
function paginated<T>(body: T) {
  return { data: body }
}

beforeEach(() => {
  getMock.mockReset()
})

describe('fetchForSelect', () => {
  it('returns the paginated body from the response', async () => {
    const body = {
      items: [{ id: 1, label: 'Jane', subtitle: 'jane@acme.test' }],
      export_link: null,
      pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 },
    }
    getMock.mockResolvedValue(paginated(body))

    const result = await fetchForSelect('users', { search: 'ja' })

    expect(result).toEqual(body)
    expect(getMock).toHaveBeenCalledWith(
      '/users/for-select',
      expect.objectContaining({
        params: { offset: 0, limit: 25, search: 'ja' },
        paramsSerializer: { indexes: true },
      }),
    )
  })

  it('omits empty search and empty ids from the request params', async () => {
    getMock.mockResolvedValue(
      paginated({
        items: [],
        export_link: null,
        pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
      }),
    )

    await fetchForSelect('users', { ids: [] })

    const [, config] = getMock.mock.calls[0]
    expect(config?.params).toEqual({ offset: 0, limit: 25 })
  })

  it('forwards non-empty ids for hydration', async () => {
    getMock.mockResolvedValue(
      paginated({
        items: [],
        export_link: null,
        pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
      }),
    )

    await fetchForSelect('users', { ids: [3, 9], offset: 50, limit: 10 })

    const [, config] = getMock.mock.calls[0]
    expect(config?.params).toEqual({ offset: 50, limit: 10, ids: [3, 9] })
  })
})
