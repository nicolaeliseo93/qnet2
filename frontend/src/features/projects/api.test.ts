import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchProjectCards, projectCardsQueryKey } from '@/features/projects/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn() },
}))

const getMock = vi.mocked(apiClient.get)

beforeEach(() => {
  getMock.mockReset()
})

describe('fetchProjectCards', () => {
  it('omits advancedFilters when none are active', async () => {
    getMock.mockResolvedValue({
      data: { items: [], pagination: { total: 0, offset: 0, limit: 12, total_pages: 0 } },
    })

    await fetchProjectCards({})

    expect(getMock).toHaveBeenCalledWith('/projects', {
      params: { offset: 0, limit: 12 },
    })
  })

  /**
   * Same `advancedFilters` shape as the AG Grid path's `POST /rows` body
   * (spec 0032 AC-018): a nested map forwarded as-is in the query params
   * (axios serializes nested objects to bracket notation by default, which
   * Laravel parses back into an associative array).
   */
  it('forwards non-empty advancedFilters as a nested query param', async () => {
    getMock.mockResolvedValue({
      data: { items: [], pagination: { total: 0, offset: 0, limit: 12, total_pages: 0 } },
    })

    await fetchProjectCards({ advancedFilters: { status: 'won' } })

    expect(getMock).toHaveBeenCalledWith('/projects', {
      params: { offset: 0, limit: 12, advancedFilters: { status: 'won' } },
    })
  })
})

describe('projectCardsQueryKey', () => {
  it('includes advancedFilters so an applied change starts a fresh query', () => {
    expect(projectCardsQueryKey({ advancedFilters: { status: 'won' } })).toEqual([
      'projects',
      'cards',
      { advancedFilters: { status: 'won' } },
    ])
  })
})
