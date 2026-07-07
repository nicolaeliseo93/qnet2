import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/sources/source-form-payload'
import type { SourceDetailWithPermissions } from '@/features/sources/types'
import type { SourceFormValues } from '@/features/sources/use-source-form'

/** Spec 0018 (mirrored on referent-types, spec 0016 AC-022): create shape, update diffs only changes. */

const formValues: SourceFormValues = { name: 'Sponsor' }

function original(overrides: Partial<SourceDetailWithPermissions> = {}): SourceDetailWithPermissions {
  return {
    id: 7,
    name: 'Sponsor',
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
    expect(buildCreatePayload(formValues)).toEqual({ name: 'Sponsor' })
  })
})

describe('buildUpdatePayload', () => {
  it('omits the field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ name: 'Partner' }, original())).toEqual({ name: 'Partner' })
  })
})
