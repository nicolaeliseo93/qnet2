import { describe, expect, it } from 'vitest'
import {
  fieldPermissionFlag,
  sameFieldPermissions,
  toggleFieldPermission,
} from '@/features/roles/field-permission-toggle'
import type { RoleFieldPermission } from '@/features/roles/types'

describe('fieldPermissionFlag', () => {
  it('defaults to unrestricted (visible+editable, not required) when no row exists', () => {
    expect(fieldPermissionFlag([], 'users', 'email', 'visible')).toBe(true)
    expect(fieldPermissionFlag([], 'users', 'email', 'editable')).toBe(true)
    expect(fieldPermissionFlag([], 'users', 'email', 'required')).toBe(false)
  })

  it('reads the flag from an existing row', () => {
    const rows: RoleFieldPermission[] = [
      { resource: 'users', field: 'email', visible: false, editable: true, required: false },
    ]
    expect(fieldPermissionFlag(rows, 'users', 'email', 'visible')).toBe(false)
  })
})

describe('toggleFieldPermission', () => {
  it('materializes a new row at the unrestricted defaults on first toggle', () => {
    const next = toggleFieldPermission([], 'users', 'email', 'visible', false)
    expect(next).toEqual([
      { resource: 'users', field: 'email', visible: false, editable: true, required: false },
    ])
  })

  it('updates the existing row in place, leaving other rows untouched', () => {
    const rows: RoleFieldPermission[] = [
      { resource: 'users', field: 'email', visible: false, editable: true, required: false },
      { resource: 'users', field: 'locale', visible: true, editable: false, required: false },
    ]
    const next = toggleFieldPermission(rows, 'users', 'email', 'editable', false)
    expect(next).toEqual([
      { resource: 'users', field: 'email', visible: false, editable: false, required: false },
      { resource: 'users', field: 'locale', visible: true, editable: false, required: false },
    ])
  })
})

describe('sameFieldPermissions', () => {
  it('is order-insensitive', () => {
    const a: RoleFieldPermission[] = [
      { resource: 'users', field: 'email', visible: false, editable: true, required: false },
      { resource: 'roles', field: 'name', visible: true, editable: false, required: false },
    ]
    const b: RoleFieldPermission[] = [a[1], a[0]]
    expect(sameFieldPermissions(a, b)).toBe(true)
  })

  it('detects a changed flag', () => {
    const a: RoleFieldPermission[] = [
      { resource: 'users', field: 'email', visible: false, editable: true, required: false },
    ]
    const b: RoleFieldPermission[] = [
      { resource: 'users', field: 'email', visible: true, editable: true, required: false },
    ]
    expect(sameFieldPermissions(a, b)).toBe(false)
  })

  it('detects a length mismatch', () => {
    const a: RoleFieldPermission[] = [
      { resource: 'users', field: 'email', visible: false, editable: true, required: false },
    ]
    expect(sameFieldPermissions(a, [])).toBe(false)
  })
})
