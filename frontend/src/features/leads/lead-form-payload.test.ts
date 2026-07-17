import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/leads/lead-form-payload'
import type { LeadDetail } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/** AC-063: the PATCH payload carries only the fields that actually changed (sparse diff, mirrors campaigns). */

function values(overrides: Partial<LeadFormValues> = {}): LeadFormValues {
  return {
    registry_id: 10,
    campaign_id: 20,
    lead_status_id: 30,
    operational_site_id: null,
    source_id: null,
    operator_id: null,
    notes: null,
    extra_fields: [],
    ...overrides,
  }
}

function original(overrides: Partial<LeadDetail> = {}): LeadDetail {
  return {
    id: 1,
    registry_id: 10,
    registry: { id: 10, name: 'Mario Rossi' },
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
    extra_fields: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('includes the required registry_id/campaign_id/lead_status_id and the 4 optional fields', () => {
    const payload = buildCreatePayload(
      values({ operational_site_id: 3, source_id: 4, operator_id: 5, notes: 'Note' }),
    )

    expect(payload).toEqual({
      registry_id: 10,
      campaign_id: 20,
      lead_status_id: 30,
      operational_site_id: 3,
      source_id: 4,
      operator_id: 5,
      notes: 'Note',
      extra_fields: null,
    })
  })

  it('sends null for the unset optional fields', () => {
    const payload = buildCreatePayload(values())

    expect(payload).toEqual({
      registry_id: 10,
      campaign_id: 20,
      lead_status_id: 30,
      operational_site_id: null,
      source_id: null,
      operator_id: null,
      notes: null,
      extra_fields: null,
    })
  })

  it('includes extra_fields as an object when rows are set (AC-014)', () => {
    const payload = buildCreatePayload(
      values({ extra_fields: [{ key: 'Original column', value: 'foo' }] }),
    )

    expect(payload.extra_fields).toEqual({ 'Original column': 'foo' })
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
      values({ registry_id: 11, operational_site_id: 7 }),
      original(),
    )
    expect(payload).toEqual({ registry_id: 11, operational_site_id: 7 })
  })

  it('includes a field cleared back to null', () => {
    const payload = buildUpdatePayload(
      values({ source_id: null }),
      original({ source_id: 4, source: { id: 4, name: 'Web' } }),
    )
    expect(payload).toEqual({ source_id: null })
  })

  it('omits extra_fields when the rows are unchanged (order-independent, AC-014)', () => {
    const payload = buildUpdatePayload(
      values({ extra_fields: [{ key: 'b', value: '2' }, { key: 'a', value: '1' }] }),
      original({ extra_fields: { a: '1', b: '2' } }),
    )
    expect(payload).toEqual({})
  })

  it('includes extra_fields when a value changed', () => {
    const payload = buildUpdatePayload(
      values({ extra_fields: [{ key: 'a', value: 'updated' }] }),
      original({ extra_fields: { a: '1' } }),
    )
    expect(payload).toEqual({ extra_fields: { a: 'updated' } })
  })

  it('includes extra_fields as null when every row is removed', () => {
    const payload = buildUpdatePayload(values({ extra_fields: [] }), original({ extra_fields: { a: '1' } }))
    expect(payload).toEqual({ extra_fields: null })
  })
})
