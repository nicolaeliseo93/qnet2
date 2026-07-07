import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/ea-sectors/ea-sector-form-payload'
import type { EaSectorDetail } from '@/features/ea-sectors/types'
import type { EaSectorFormValues } from '@/features/ea-sectors/use-ea-sector-form'

/** Spec 0018 AC-019: create shape, update sends only the changed fields. */

function original(overrides: Partial<EaSectorDetail> = {}): EaSectorDetail {
  return {
    id: 4,
    name: 'Applications',
    parent_id: 1,
    parent: { id: 1, name: 'Enterprise Architecture' },
    created_at: '2026-01-01T00:00:00Z',
    tag_ids: [],
    tags: [],
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the create payload with a root parent_id (null)', () => {
    const values: EaSectorFormValues = { name: 'Applications', parent_id: null, tag_ids: [] }

    expect(buildCreatePayload(values)).toEqual({
      name: 'Applications',
      parent_id: null,
      tag_ids: [],
    })
  })

  it('builds the create payload with a selected parent', () => {
    const values: EaSectorFormValues = { name: 'Applications', parent_id: 1, tag_ids: [] }

    expect(buildCreatePayload(values)).toEqual({ name: 'Applications', parent_id: 1, tag_ids: [] })
  })

  it('always carries tag_ids', () => {
    const values: EaSectorFormValues = { name: 'Applications', parent_id: null, tag_ids: [1, 2] }

    expect(buildCreatePayload(values).tag_ids).toEqual([1, 2])
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const values: EaSectorFormValues = { name: 'Applications', parent_id: 1, tag_ids: [] }

    expect(buildUpdatePayload(values, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    const values: EaSectorFormValues = { name: 'Business Applications', parent_id: 1, tag_ids: [] }

    expect(buildUpdatePayload(values, original())).toEqual({ name: 'Business Applications' })
  })

  it('includes only the changed parent_id, including a move to root (null)', () => {
    const values: EaSectorFormValues = { name: 'Applications', parent_id: null, tag_ids: [] }

    expect(buildUpdatePayload(values, original())).toEqual({ parent_id: null })
  })

  it('includes both fields when both changed', () => {
    const values: EaSectorFormValues = { name: 'Business Applications', parent_id: 2, tag_ids: [] }

    expect(buildUpdatePayload(values, original())).toEqual({
      name: 'Business Applications',
      parent_id: 2,
    })
  })

  it('sends tag_ids only when the selection actually changed (order-insensitive)', () => {
    const unchanged: EaSectorFormValues = { name: 'Applications', parent_id: 1, tag_ids: [2, 1] }
    expect(buildUpdatePayload(unchanged, original({ tag_ids: [1, 2] })).tag_ids).toBeUndefined()

    const changed: EaSectorFormValues = { name: 'Applications', parent_id: 1, tag_ids: [1, 3] }
    expect(buildUpdatePayload(changed, original({ tag_ids: [1, 2] })).tag_ids).toEqual([1, 3])
  })
})
