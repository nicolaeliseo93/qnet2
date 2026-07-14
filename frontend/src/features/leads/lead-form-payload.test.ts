import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/leads/lead-form-payload'
import type { LeadDetail } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/** AC-063: the PATCH payload carries only the fields that actually changed (sparse diff, mirrors campaigns). */

function values(overrides: Partial<LeadFormValues> = {}): LeadFormValues {
  return {
    referent_id: 10,
    campaign_id: 20,
    lead_status_id: 30,
    operational_site_id: null,
    source_id: null,
    operator_id: null,
    notes: null,
    ...overrides,
  }
}

function original(overrides: Partial<LeadDetail> = {}): LeadDetail {
  return {
    id: 1,
    referent_id: 10,
    referent: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status_id: 30,
    lead_status: { id: 30, name: 'New', color: 'slate' },
    operational_site_id: null,
    operational_site: null,
    source_id: null,
    source: null,
    operator_id: null,
    operator: null,
    notes: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('includes the required referent_id/campaign_id/lead_status_id and the 4 optional fields', () => {
    const payload = buildCreatePayload(
      values({ operational_site_id: 3, source_id: 4, operator_id: 5, notes: 'Note' }),
    )

    expect(payload).toEqual({
      referent_id: 10,
      campaign_id: 20,
      lead_status_id: 30,
      operational_site_id: 3,
      source_id: 4,
      operator_id: 5,
      notes: 'Note',
    })
  })

  it('sends null for the unset optional fields', () => {
    const payload = buildCreatePayload(values())

    expect(payload).toEqual({
      referent_id: 10,
      campaign_id: 20,
      lead_status_id: 30,
      operational_site_id: null,
      source_id: null,
      operator_id: null,
      notes: null,
    })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(values(), original())).toEqual({})
  })

  it('includes only the changed notes', () => {
    expect(buildUpdatePayload(values({ notes: 'Updated note' }), original())).toEqual({
      notes: 'Updated note',
    })
  })

  it('includes only the changed campaign_id', () => {
    expect(buildUpdatePayload(values({ campaign_id: 99 }), original())).toEqual({ campaign_id: 99 })
  })

  it('includes only the changed lead_status_id', () => {
    expect(buildUpdatePayload(values({ lead_status_id: 99 }), original())).toEqual({
      lead_status_id: 99,
    })
  })

  it('includes multiple changed fields together', () => {
    const payload = buildUpdatePayload(
      values({ referent_id: 11, operational_site_id: 7 }),
      original(),
    )
    expect(payload).toEqual({ referent_id: 11, operational_site_id: 7 })
  })

  it('includes a field cleared back to null', () => {
    const payload = buildUpdatePayload(
      values({ source_id: null }),
      original({ source_id: 4, source: { id: 4, name: 'Web' } }),
    )
    expect(payload).toEqual({ source_id: null })
  })
})
