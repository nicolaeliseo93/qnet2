import { describe, expect, it } from 'vitest'
import { forSelectKeys } from '@/features/for-select/query-keys'

describe('forSelectKeys', () => {
  it('builds stable, hierarchical keys', () => {
    expect(forSelectKeys.all).toEqual(['for-select'])
    expect(forSelectKeys.resource('users')).toEqual(['for-select', 'users'])
    expect(forSelectKeys.list('users', 'jane')).toEqual([
      'for-select',
      'users',
      { search: 'jane' },
    ])
  })
})
