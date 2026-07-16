import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/status-groups/status-group-form-payload'
import type { StatusGroupDetailWithPermissions } from '@/features/status-groups/types'
import type { StatusGroupFormValues } from '@/features/status-groups/use-status-group-form'

/** Spec 0039 (mirrors lead-statuses, spec 0029): create shape, update diffs only changes. */

const formValues: StatusGroupFormValues = {
  name: 'Open',
  color: 'blue',
  sort_order: 1,
  custom_fields: {},
}

function original(
  overrides: Partial<StatusGroupDetailWithPermissions> = {},
): StatusGroupDetailWithPermissions {
  return {
    id: 7,
    name: 'Open',
    color: 'blue',
    sort_order: 1,
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
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'Open',
      color: 'blue',
      sort_order: 1,
    })
  })

  it('maps an empty color to null', () => {
    expect(buildCreatePayload({ ...formValues, color: '' })).toEqual({
      name: 'Open',
      color: null,
      sort_order: 1,
    })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Closed' }, original())).toEqual({
      name: 'Closed',
    })
  })

  it('includes only the changed color, mapping empty to null', () => {
    expect(buildUpdatePayload({ ...formValues, color: '' }, original())).toEqual({
      color: null,
    })
  })

  it('includes only the changed sort_order', () => {
    expect(buildUpdatePayload({ ...formValues, sort_order: 5 }, original())).toEqual({
      sort_order: 5,
    })
  })
})
