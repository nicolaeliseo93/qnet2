import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/lead-statuses/lead-status-form-payload'
import type { LeadStatusDetailWithPermissions } from '@/features/lead-statuses/types'
import type { LeadStatusFormValues } from '@/features/lead-statuses/use-lead-status-form'

/** Spec 0029 (mirrored on project-statuses, spec 0023 AC-014): create shape, update diffs only changes. */

const formValues: LeadStatusFormValues = {
  name: 'Draft',
  color: 'blue',
  sort_order: 1,
  custom_fields: {},
}

function original(
  overrides: Partial<LeadStatusDetailWithPermissions> = {},
): LeadStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Draft',
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
      name: 'Draft',
      color: 'blue',
      sort_order: 1,
    })
  })

  it('maps an empty color to null', () => {
    expect(buildCreatePayload({ ...formValues, color: '' })).toEqual({
      name: 'Draft',
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
    expect(buildUpdatePayload({ ...formValues, name: 'Active' }, original())).toEqual({
      name: 'Active',
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
