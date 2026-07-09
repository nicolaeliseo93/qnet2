import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/referent-types/referent-type-form-payload'
import type { ReferentTypeDetailWithPermissions } from '@/features/referent-types/types'
import type { ReferentTypeFormValues } from '@/features/referent-types/use-referent-type-form'

/** Spec 0016 AC-022 (mirrored on referent-types): create shape, update diffs only changes. */

const formValues: ReferentTypeFormValues = { name: 'Sponsor', custom_fields: {} }

function original(
  overrides: Partial<ReferentTypeDetailWithPermissions> = {},
): ReferentTypeDetailWithPermissions {
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
    expect(buildUpdatePayload({ name: 'Partner', custom_fields: {} }, original())).toEqual({
      name: 'Partner',
    })
  })
})
