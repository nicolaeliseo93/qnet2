import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/business-functions/business-function-form-payload'
import type { BusinessFunctionDetailWithPermissions } from '@/features/business-functions/types'
import type { BusinessFunctionFormValues } from '@/features/business-functions/use-business-function-form'

/** Spec 0010 AC-019: create payload shape, update payload diffs only changes. */

const formValues: BusinessFunctionFormValues = {
  name: 'Engineering',
  type: 'business_unit',
  manager_id: 1,
  users: [2, 3],
}

function original(
  overrides: Partial<BusinessFunctionDetailWithPermissions> = {},
): BusinessFunctionDetailWithPermissions {
  return {
    id: 7,
    name: 'Engineering',
    is_business_unit: true,
    is_business_service: false,
    type: 'business_unit',
    manager_id: 1,
    manager: { id: 1, name: 'Ada Lovelace', avatar_url: null },
    user_ids: [2, 3],
    users: [
      { id: 2, name: 'Grace Hopper', avatar_url: null },
      { id: 3, name: 'Alan Turing', avatar_url: null },
    ],
    created_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the full create payload shape', () => {
    const payload = buildCreatePayload(formValues)

    expect(payload).toEqual({
      name: 'Engineering',
      type: 'business_unit',
      manager_id: 1,
      users: [2, 3],
    })
  })

  it('carries a null type and manager_id through unchanged', () => {
    const payload = buildCreatePayload({ ...formValues, type: null, manager_id: null, users: [] })

    expect(payload.type).toBeNull()
    expect(payload.manager_id).toBeNull()
    expect(payload.users).toEqual([])
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const payload = buildUpdatePayload(formValues, original())

    expect(payload).toEqual({})
  })

  it('includes only the changed name', () => {
    const payload = buildUpdatePayload({ ...formValues, name: 'Platform' }, original())

    expect(payload).toEqual({ name: 'Platform' })
  })

  it('includes users only when the id set actually differs (order-insensitive)', () => {
    const reordered = buildUpdatePayload({ ...formValues, users: [3, 2] }, original())
    expect(reordered).toEqual({})

    const changed = buildUpdatePayload({ ...formValues, users: [2, 4] }, original())
    expect(changed).toEqual({ users: [2, 4] })
  })

  it('sends manager_id: null when the responsabile is cleared', () => {
    const payload = buildUpdatePayload({ ...formValues, manager_id: null }, original())

    expect(payload).toEqual({ manager_id: null })
  })

  it('remaps type when the selection changes, including to/from null', () => {
    const toService = buildUpdatePayload({ ...formValues, type: 'business_service' }, original())
    expect(toService).toEqual({ type: 'business_service' })

    const toNone = buildUpdatePayload({ ...formValues, type: null }, original())
    expect(toNone).toEqual({ type: null })
  })

  it('combines multiple changed fields in a single payload', () => {
    const payload = buildUpdatePayload(
      { name: 'Platform', type: 'business_service', manager_id: null, users: [9] },
      original(),
    )

    expect(payload).toEqual({
      name: 'Platform',
      type: 'business_service',
      manager_id: null,
      users: [9],
    })
  })
})
