import { describe, expect, it, vi, beforeEach } from 'vitest'
import { renderHook } from '@testing-library/react'
import {
  fetchUsersForSelect,
  useUsersForSelect,
  USERS_FOR_SELECT_RESOURCE,
} from '@/features/users/for-select-api'

const fetchForSelect = vi.fn()
const useForSelect = vi.fn()

vi.mock('@/features/for-select/api', () => ({
  fetchForSelect: (...args: unknown[]) => fetchForSelect(...args),
}))
vi.mock('@/features/for-select/use-for-select', () => ({
  useForSelect: (args: unknown) => useForSelect(args),
}))

beforeEach(() => {
  fetchForSelect.mockReset()
  useForSelect.mockReset()
})

describe('users for-select wrappers', () => {
  it('fetches against the users resource', async () => {
    fetchForSelect.mockResolvedValue({ items: [] })
    await fetchUsersForSelect({ search: 'jo' })
    expect(fetchForSelect).toHaveBeenCalledWith(USERS_FOR_SELECT_RESOURCE, {
      search: 'jo',
    })
  })

  it('binds the hook to the users resource', () => {
    useForSelect.mockReturnValue({})
    renderHook(() => useUsersForSelect({ search: 'a', ids: [1], enabled: true }))
    expect(useForSelect).toHaveBeenCalledWith({
      resource: USERS_FOR_SELECT_RESOURCE,
      search: 'a',
      ids: [1],
      enabled: true,
    })
  })
})
