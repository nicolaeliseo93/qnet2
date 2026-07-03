import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchTableColumnValues } from '@/features/table/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { post: vi.fn() },
}))

const postMock = vi.mocked(apiClient.post)

beforeEach(() => {
  postMock.mockReset()
})

describe('fetchTableColumnValues', () => {
  it('posts the payload and unwraps the ok() envelope', async () => {
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: { values: ['a', 'b'], hasMore: false } },
    })

    const result = await fetchTableColumnValues('users', {
      columnId: 'email',
      filterModel: { roles: { filterType: 'set', values: ['admin'] } },
    })

    expect(result).toEqual({ values: ['a', 'b'], hasMore: false })
    expect(postMock).toHaveBeenCalledWith('/tables/users/values', {
      columnId: 'email',
      filterModel: { roles: { filterType: 'set', values: ['admin'] } },
    })
  })
})
