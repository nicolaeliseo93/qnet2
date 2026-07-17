import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/opportunities/opportunity-form-payload'
import type { OpportunityDetail } from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/** AC-076: the PATCH payload carries only the fields that actually changed (sparse diff, mirrors leads/registries). */

function values(overrides: Partial<OpportunityFormValues> = {}): OpportunityFormValues {
  return {
    name: 'Enterprise deal',
    registry_id: 1,
    referent_id: null,
    commercial_id: null,
    reporter_id: null,
    supervisor_id: null,
    source_id: null,
    product_lines: [],
    manager_slots: [],
    start_date: null,
    expected_close_date: null,
    estimated_value: null,
    // A-6: the form always holds a number (default 0) — never null.
    success_probability: 0,
    ...overrides,
  }
}

function original(overrides: Partial<OpportunityDetail> = {}): OpportunityDetail {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry_id: 1,
    registry: { id: 1, name: 'Acme S.p.A.' },
    referent_id: null,
    referent: null,
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    source_id: null,
    source: null,
    product_lines: [],
    lead_id: null,
    lead: null,
    managers: [],
    start_date: null,
    estimated_value: null,
    expected_close_date: null,
    success_probability: null,
    locked_fields: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('includes the 2 mandatory fields and every truly optional field as null when unset (AC-082)', () => {
    const payload = buildCreatePayload(values())

    expect(payload).toEqual({
      name: 'Enterprise deal',
      registry_id: 1,
      referent_id: null,
      commercial_id: null,
      reporter_id: null,
      supervisor_id: null,
      source_id: null,
      product_lines: [],
      manager_slots: [],
      start_date: null,
      expected_close_date: null,
      estimated_value: null,
      success_probability: 0,
    })
  })

  /** Amendment rev.3 (AC-099/107): `product_lines` is always sent in full, never locked, even from a lead. */
  it('always sends product_lines in full, unlocked even when creating from a lead', () => {
    const payload = buildCreatePayload(
      values({
        product_lines: [
          { business_function_id: 40, product_category_id: 50 },
          { business_function_id: 41, product_category_id: 51 },
        ],
      }),
      { leadId: 9, lockedFields: ['registry_id'] },
    )

    expect(payload.product_lines).toEqual([
      { business_function_id: 40, product_category_id: 50 },
      { business_function_id: 41, product_category_id: 51 },
    ])
  })

  it('trims the name and includes every set relation/estimate', () => {
    const payload = buildCreatePayload(
      values({
        name: '  Enterprise deal  ',
        referent_id: 10,
        manager_slots: [20, null, 21],
        start_date: '2026-01-01',
        estimated_value: 5000,
        success_probability: 40,
      }),
    )

    expect(payload.name).toBe('Enterprise deal')
    expect(payload.referent_id).toBe(10)
    expect(payload.manager_slots).toEqual([20, null, 21])
    expect(payload.start_date).toBe('2026-01-01')
    expect(payload.estimated_value).toBe(5000)
    expect(payload.success_probability).toBe(40)
  })

  /** AC-075: creating from a Lead appends `lead_id` and OMITS every locked field entirely (BR-1/BR-2), never merely repeats it. */
  describe('create-from-lead (BR-1/BR-2, AC-075)', () => {
    it('appends lead_id and omits every locked field from the payload', () => {
      const payload = buildCreatePayload(
        values({
          registry_id: 30,
          referent_id: 10,
          source_id: 20,
        }),
        {
          leadId: 9,
          lockedFields: ['registry_id', 'referent_id', 'source_id'],
        },
      )

      expect(payload.lead_id).toBe(9)
      expect(payload).not.toHaveProperty('registry_id')
      expect(payload).not.toHaveProperty('referent_id')
      expect(payload).not.toHaveProperty('source_id')
    })

    it('sends a field whose derivation is null (BR-2: not locked, stays free) even from a lead', () => {
      const payload = buildCreatePayload(
        values({ source_id: 70 }),
        { leadId: 9, lockedFields: ['registry_id', 'referent_id'] },
      )

      expect(payload.source_id).toBe(70)
      expect(payload).not.toHaveProperty('registry_id')
      expect(payload).not.toHaveProperty('referent_id')
    })

    it('never appends lead_id for a plain manual create', () => {
      const payload = buildCreatePayload(values())
      expect(payload).not.toHaveProperty('lead_id')
    })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(values(), original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload(values({ name: 'Renamed deal' }), original())).toEqual({
      name: 'Renamed deal',
    })
  })

  it('includes only the changed registry_id', () => {
    expect(buildUpdatePayload(values({ registry_id: 2 }), original())).toEqual({ registry_id: 2 })
  })

  it('includes a relation cleared back to null', () => {
    const payload = buildUpdatePayload(
      values({ source_id: null }),
      original({ source_id: 4, source: { id: 4, name: 'Web' } }),
    )
    expect(payload).toEqual({ source_id: null })
  })

  it('includes multiple changed fields together', () => {
    const payload = buildUpdatePayload(
      values({ referent_id: 11, source_id: 7 }),
      original(),
    )
    expect(payload).toEqual({ referent_id: 11, source_id: 7 })
  })

  it('omits estimated_value when the server decimal string round-trips to the same number', () => {
    const payload = buildUpdatePayload(
      values({ estimated_value: 1500 }),
      original({ estimated_value: '1500.00' }),
    )
    expect(payload).toEqual({})
  })

  it('includes estimated_value when it actually changed', () => {
    const payload = buildUpdatePayload(
      values({ estimated_value: 2000 }),
      original({ estimated_value: '1500.00' }),
    )
    expect(payload).toEqual({ estimated_value: 2000 })
  })

  it('includes success_probability when changed', () => {
    const payload = buildUpdatePayload(
      values({ success_probability: 80 }),
      original({ success_probability: 40 }),
    )
    expect(payload).toEqual({ success_probability: 80 })
  })

  describe('product_lines (unordered set diff, amendment rev.3)', () => {
    it('omits product_lines when the set is unchanged, even reordered', () => {
      const payload = buildUpdatePayload(
        values({
          product_lines: [
            { business_function_id: 2, product_category_id: 22 },
            { business_function_id: 1, product_category_id: 11 },
          ],
        }),
        original({
          product_lines: [
            {
              id: 100,
              business_function: { id: 1, name: 'Sales' },
              product_category: { id: 11, name: 'Consulting' },
            },
            {
              id: 101,
              business_function: { id: 2, name: 'Marketing' },
              product_category: { id: 22, name: 'Training' },
            },
          ],
        }),
      )
      expect(payload).toEqual({})
    })

    it('includes product_lines when a row was added', () => {
      const payload = buildUpdatePayload(
        values({ product_lines: [{ business_function_id: 1, product_category_id: 11 }] }),
        original({ product_lines: [] }),
      )
      expect(payload).toEqual({ product_lines: [{ business_function_id: 1, product_category_id: 11 }] })
    })

    it('includes product_lines when every row is removed', () => {
      const payload = buildUpdatePayload(
        values({ product_lines: [] }),
        original({
          product_lines: [
            {
              id: 100,
              business_function: { id: 1, name: 'Sales' },
              product_category: { id: 11, name: 'Consulting' },
            },
          ],
        }),
      )
      expect(payload).toEqual({ product_lines: [] })
    })
  })

  describe('manager_slots (position- and gap-sensitive, rebuilt from `managers` refs)', () => {
    it('omits manager_slots when the form slots match the original managers, in position', () => {
      const payload = buildUpdatePayload(
        values({ manager_slots: [30, null, 31] }),
        original({
          managers: [
            { id: 30, name: 'Mario Rossi', position: 1 },
            { id: 31, name: 'Anna Bianchi', position: 3 },
          ],
        }),
      )
      expect(payload).toEqual({})
    })

    it('includes manager_slots when a slot changed', () => {
      const payload = buildUpdatePayload(
        values({ manager_slots: [32] }),
        original({ managers: [{ id: 30, name: 'Mario Rossi', position: 1 }] }),
      )
      expect(payload).toEqual({ manager_slots: [32] })
    })

    it('includes manager_slots when every manager is removed', () => {
      const payload = buildUpdatePayload(
        values({ manager_slots: [] }),
        original({ managers: [{ id: 30, name: 'Mario Rossi', position: 1 }] }),
      )
      expect(payload).toEqual({ manager_slots: [] })
    })
  })
})
