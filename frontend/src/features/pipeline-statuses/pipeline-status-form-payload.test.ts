import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/pipeline-statuses/pipeline-status-form-payload'
import type { PipelineStatusDetailWithPermissions } from '@/features/pipeline-statuses/types'
import type { PipelineStatusFormValues } from '@/features/pipeline-statuses/use-pipeline-status-form'

/**
 * Spec 0023 (mirrored on sources, spec 0018 AC-022): create shape, update
 * diffs only changes. Spec 0039 D-5/D-6: `sort_order` dropped (server-managed),
 * `status_group_id` added.
 */

const formValues: PipelineStatusFormValues = {
  name: 'Draft',
  color: 'blue',
  status_group_id: 2,
  custom_fields: {},
}

function original(
  overrides: Partial<PipelineStatusDetailWithPermissions> = {},
): PipelineStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Draft',
    color: 'blue',
    sort_order: 10,
    system_key: null,
    status_group_id: 2,
    status_group: { id: 2, name: 'Open', color: 'blue' },
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
      status_group_id: 2,
    })
  })

  it('maps an empty color to null', () => {
    expect(buildCreatePayload({ ...formValues, color: '' })).toEqual({
      name: 'Draft',
      color: null,
      status_group_id: 2,
    })
  })

  it('never includes sort_order (spec 0039 D-5: server-managed)', () => {
    expect(buildCreatePayload(formValues)).not.toHaveProperty('sort_order')
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

  it('includes only the changed status_group_id', () => {
    expect(buildUpdatePayload({ ...formValues, status_group_id: 5 }, original())).toEqual({
      status_group_id: 5,
    })
  })
})
