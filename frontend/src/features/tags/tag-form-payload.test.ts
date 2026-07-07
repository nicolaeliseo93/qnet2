import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/tags/tag-form-payload'
import type { TagDetailWithPermissions } from '@/features/tags/types'
import type { TagFormValues } from '@/features/tags/use-tag-form'

/** Spec 0019 (mirrored on referent-types AC-022): create shape, update diffs only changes. */

const formValues: TagFormValues = { name: 'VIP' }

function original(overrides: Partial<TagDetailWithPermissions> = {}): TagDetailWithPermissions {
  return {
    id: 7,
    name: 'VIP',
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
    expect(buildCreatePayload(formValues)).toEqual({ name: 'VIP' })
  })
})

describe('buildUpdatePayload', () => {
  it('omits the field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ name: 'Priority' }, original())).toEqual({ name: 'Priority' })
  })
})
