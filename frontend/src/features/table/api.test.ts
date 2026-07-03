import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  fetchTableColumnValues,
  resetTableFilters,
  saveTableFilters,
} from '@/features/table/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { post: vi.fn(), delete: vi.fn() },
}))

const postMock = vi.mocked(apiClient.post)
const deleteMock = vi.mocked(apiClient.delete)

beforeEach(() => {
  postMock.mockReset()
  deleteMock.mockReset()
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

describe('saveTableFilters', () => {
  it('posts the filter model wrapped and returns the merged config', async () => {
    const config = { resource: 'users', filtersCustomized: true }
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: config },
    })
    const filterModel = { email: { filterType: 'text', type: 'contains', filter: 'a' } }

    const result = await saveTableFilters('users', filterModel)

    expect(result).toBe(config)
    expect(postMock).toHaveBeenCalledWith('/tables/users/filters', { filterModel })
  })
})

describe('resetTableFilters', () => {
  it('deletes the saved filters for the domain', async () => {
    deleteMock.mockResolvedValue({ data: null })

    await resetTableFilters('users')

    expect(deleteMock).toHaveBeenCalledWith('/tables/users/filters')
  })
})
