import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/projects/project-form-payload'
import type { ProjectDetail } from '@/features/projects/types'
import type { ProjectFormValues } from '@/features/projects/use-project-form'

/**
 * Spec 0023: create shape and the update sparse diff. Spec 0025 AC-010/AC-011:
 * the create payload carries a manual `code` only when set (never blank), the
 * update payload never carries it (immutable after create).
 */

function values(overrides: Partial<ProjectFormValues> = {}): ProjectFormValues {
  return {
    name: 'Acme rollout',
    description: null,
    registry_id: null,
    pipeline_status_id: 3,
    source_id: null,
    business_function_id: null,
    country_id: 1,
    state_id: null,
    province_id: null,
    city_id: null,
    product_category_id: null,
    partner_id: null,
    start_date: '',
    end_date: '',
    total_budget: null,
    target_lead: null,
    custom_fields: {},
    ...overrides,
  }
}

function original(overrides: Partial<ProjectDetail> = {}): ProjectDetail {
  return {
    id: 4,
    code: 'PRJ-0004',
    name: 'Acme rollout',
    description: null,
    registry_id: null,
    registry: null,
    pipeline_status_id: 3,
    pipeline_status: { id: 3, name: 'Active', color: 'blue' },
    source_id: null,
    source: null,
    business_function_id: null,
    business_function: null,
    country_id: 1,
    country: { id: 1, name: 'Italy' },
    state_id: null,
    state: null,
    province_id: null,
    province: null,
    city_id: null,
    city: null,
    geo_scope: 'country',
    product_category_id: null,
    product_category: null,
    partner_id: null,
    partner: null,
    start_date: null,
    end_date: null,
    total_budget: null,
    target_lead: null,
    allocated_budget: '0.00',
    remaining_budget: null,
    campaigns_count: 0,
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the full create payload shape without a code field when unset (AC-010)', () => {
    const payload = buildCreatePayload(values())

    expect(payload).toEqual({
      name: 'Acme rollout',
      pipeline_status_id: 3,
      description: null,
      registry_id: null,
      source_id: null,
      business_function_id: null,
      country_id: 1,
      state_id: null,
      province_id: null,
      city_id: null,
      product_category_id: null,
      partner_id: null,
      start_date: null,
      end_date: null,
      total_budget: null,
      target_lead: null,
    })
    expect(payload).not.toHaveProperty('code')
  })

  it('includes a trimmed manual code when set (AC-010)', () => {
    const payload = buildCreatePayload(values({ code: '  ACME-2026  ' }))
    expect(payload.code).toBe('ACME-2026')
  })

  it('omits code when it is blank after trimming (empty equals absent, AC-003)', () => {
    const payload = buildCreatePayload(values({ code: '   ' }))
    expect(payload).not.toHaveProperty('code')
  })

  it('maps empty date strings to null and keeps numeric fields', () => {
    const payload = buildCreatePayload(
      values({ start_date: '2026-01-01', end_date: '2026-03-01', total_budget: 1000, target_lead: 25 }),
    )

    expect(payload.start_date).toBe('2026-01-01')
    expect(payload.end_date).toBe('2026-03-01')
    expect(payload.total_budget).toBe(1000)
    expect(payload.target_lead).toBe(25)
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(values(), original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload(values({ name: 'Renamed' }), original())).toEqual({ name: 'Renamed' })
  })

  it('includes only the changed pipeline_status_id', () => {
    expect(buildUpdatePayload(values({ pipeline_status_id: 9 }), original())).toEqual({
      pipeline_status_id: 9,
    })
  })

  it('includes the changed total_budget, diffed against the string-typed original', () => {
    const payload = buildUpdatePayload(
      values({ total_budget: 500 }),
      original({ total_budget: '1000.00' }),
    )
    expect(payload).toEqual({ total_budget: 500 })
  })

  it('omits total_budget when it numerically matches the string-typed original', () => {
    const payload = buildUpdatePayload(
      values({ total_budget: 1000 }),
      original({ total_budget: '1000.00' }),
    )
    expect(payload).toEqual({})
  })

  it('maps a cleared date back to null in the diff', () => {
    const payload = buildUpdatePayload(values({ start_date: '' }), original({ start_date: '2026-01-01' }))
    expect(payload).toEqual({ start_date: null })
  })

  it('never includes a code field, even when the form value differs from the original (AC-011)', () => {
    const payload = buildUpdatePayload(values({ name: 'Renamed', code: 'SOMETHING-ELSE' }), original())
    expect(payload).not.toHaveProperty('code')
  })

  it('includes only the changed geo levels (spec 0027 BR-4)', () => {
    const payload = buildUpdatePayload(
      values({ state_id: 10, city_id: 100 }),
      original({ state_id: null, city_id: null }),
    )
    expect(payload).toEqual({ state_id: 10, city_id: 100 })
  })
})
