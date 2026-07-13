import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/project-statuses/project-status-form-payload'
import type { ProjectStatusDetailWithPermissions } from '@/features/project-statuses/types'
import type { ProjectStatusFormValues } from '@/features/project-statuses/use-project-status-form'

/** Spec 0023 (mirrored on sources, spec 0018 AC-022): create shape, update diffs only changes. */

const formValues: ProjectStatusFormValues = {
  name: 'Draft',
  color: 'blue',
  sort_order: 1,
  custom_fields: {},
}

function original(
  overrides: Partial<ProjectStatusDetailWithPermissions> = {},
): ProjectStatusDetailWithPermissions {
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
