import { describe, expect, it } from 'vitest'
import { groupPermissions, permissionAbility } from './permission-groups'

describe('permissionAbility', () => {
  it('returns the ability suffix after the first dot', () => {
    expect(permissionAbility('users.view')).toBe('view')
    expect(permissionAbility('users.viewAny')).toBe('viewAny')
  })

  it('returns the whole string when there is no dot', () => {
    expect(permissionAbility('dashboard')).toBe('dashboard')
  })

  it('only splits on the first dot', () => {
    expect(permissionAbility('reports.export.csv')).toBe('export.csv')
  })
})

describe('groupPermissions', () => {
  it('groups by resource prefix, preserving first-seen order', () => {
    const result = groupPermissions([
      'users.view',
      'roles.view',
      'users.create',
      'roles.delete',
    ])

    expect(result).toEqual([
      { resource: 'users', permissions: ['users.view', 'users.create'] },
      { resource: 'roles', permissions: ['roles.view', 'roles.delete'] },
    ])
  })

  it('puts dot-less permissions under the "general" group', () => {
    expect(groupPermissions(['dashboard', 'users.view'])).toEqual([
      { resource: 'general', permissions: ['dashboard'] },
      { resource: 'users', permissions: ['users.view'] },
    ])
  })

  it('returns an empty array for no permissions', () => {
    expect(groupPermissions([])).toEqual([])
  })
})
