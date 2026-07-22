import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildRequestWorkSchema } from '@/features/request-management/request-work-schema'
import type { RequestWorkflowStatusRef } from '@/features/request-management/types'

/**
 * Spec 0054 D-5: mirrors `RequestManagementService::updateWork()`'s server
 * rule client-side — a note is mandatory only when the working status
 * BOTH changes AND its target is flagged `requires_note`. The server stays
 * authoritative; this is the anticipatory half.
 */

const STATUSES: RequestWorkflowStatusRef[] = [
  { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
  { id: 101, name: 'Closed', color: 'green', system_key: null, description: null, requires_note: true },
]

function values(overrides: Record<string, unknown> = {}) {
  return {
    opportunity_workflow_status_id: 100,
    next_callback_at: null,
    note: '',
    client_identity: null,
    client_contacts: [],
    client_address: [],
    products_of_interest: [],
    source_id: null,
    reporter_id: null,
    operator_id: null,
    attribute_values: {},
    ...overrides,
  }
}

describe('buildRequestWorkSchema — note requirement (spec 0054 D-5)', () => {
  it('requires a non-blank note when moving to a status flagged requires_note', () => {
    const schema = buildRequestWorkSchema([], STATUSES, 100, i18n.t)
    const result = schema.safeParse(values({ opportunity_workflow_status_id: 101, note: '' }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'note')).toBe(true)
    }
  })

  it('accepts a blank note whitespace-only as still missing', () => {
    const schema = buildRequestWorkSchema([], STATUSES, 100, i18n.t)
    const result = schema.safeParse(values({ opportunity_workflow_status_id: 101, note: '   ' }))

    expect(result.success).toBe(false)
  })

  it('passes once the note is filled in', () => {
    const schema = buildRequestWorkSchema([], STATUSES, 100, i18n.t)
    const result = schema.safeParse(
      values({ opportunity_workflow_status_id: 101, note: 'Client confirmed by phone.' }),
    )

    expect(result.success).toBe(true)
  })

  it('does not require a note when the target status does not require one', () => {
    const schema = buildRequestWorkSchema([], STATUSES, 101, i18n.t)
    const result = schema.safeParse(values({ opportunity_workflow_status_id: 100, note: '' }))

    expect(result.success).toBe(true)
  })

  it('does not require a note when the status has not changed, even if it requires one', () => {
    const schema = buildRequestWorkSchema([], STATUSES, 101, i18n.t)
    const result = schema.safeParse(values({ opportunity_workflow_status_id: 101, note: '' }))

    expect(result.success).toBe(true)
  })
})
