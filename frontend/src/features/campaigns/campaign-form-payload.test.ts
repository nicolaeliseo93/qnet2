import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/campaigns/campaign-form-payload'
import type { CampaignDetail } from '@/features/campaigns/types'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'

/** Spec 0023: create shape (never carries `code`, BR-1) and BR-2's exclusion of the 4 classification fields when linked. */

function values(overrides: Partial<CampaignFormValues> = {}): CampaignFormValues {
  return {
    project_id: null,
    name: 'Spring push',
    description: null,
    registry_id: null,
    source_id: null,
    partner_id: null,
    project_status_id: 1,
    business_function_id: 2,
    state_id: 3,
    product_category_id: 4,
    start_date: '',
    end_date: '',
    total_budget: null,
    target_lead: null,
    custom_fields: {},
    ...overrides,
  }
}

function original(overrides: Partial<CampaignDetail> = {}): CampaignDetail {
  return {
    id: 9,
    code: 'CMP-0009',
    project_id: null,
    project: null,
    name: 'Spring push',
    description: null,
    registry_id: null,
    registry: null,
    source_id: null,
    source: null,
    partner_id: null,
    partner: null,
    derived_from_project: false,
    project_status_id: 1,
    project_status: { id: 1, name: 'Active', color: 'blue' },
    business_function_id: 2,
    business_function: { id: 2, name: 'Sales' },
    state_id: 3,
    state: { id: 3, name: 'Lombardy' },
    product_category_id: 4,
    product_category: { id: 4, name: 'Hardware' },
    start_date: null,
    end_date: null,
    total_budget: null,
    target_lead: null,
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload — standalone (BR-2)', () => {
  it('includes the 4 classification fields when project_id is null', () => {
    const payload = buildCreatePayload(values())

    expect(payload).toEqual({
      name: 'Spring push',
      project_id: null,
      description: null,
      registry_id: null,
      source_id: null,
      partner_id: null,
      project_status_id: 1,
      business_function_id: 2,
      state_id: 3,
      product_category_id: 4,
      start_date: null,
      end_date: null,
      total_budget: null,
      target_lead: null,
    })
    expect(payload).not.toHaveProperty('code')
  })
})

describe('buildCreatePayload — linked (BR-2/AC-020/AC-042)', () => {
  it('omits all 4 classification fields when project_id is set, even if the form still holds values', () => {
    const payload = buildCreatePayload(values({ project_id: 7 }))

    expect(payload).not.toHaveProperty('project_status_id')
    expect(payload).not.toHaveProperty('business_function_id')
    expect(payload).not.toHaveProperty('state_id')
    expect(payload).not.toHaveProperty('product_category_id')
    expect(payload.project_id).toBe(7)
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(values(), original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload(values({ name: 'Renamed' }), original())).toEqual({ name: 'Renamed' })
  })

  it('never includes a code field', () => {
    const payload = buildUpdatePayload(values({ name: 'Renamed' }), original())
    expect(payload).not.toHaveProperty('code')
  })

  it('includes all 4 classification fields on a linked→standalone transition, regardless of diff against the effective original', () => {
    // The campaign WAS linked (its own DB columns were NULL; `original` exposes
    // the project's effective values) and the user now unlinks it, keeping the
    // exact same ids the project had — a naive diff would wrongly omit them.
    const linkedOriginal = original({
      project_id: 5,
      derived_from_project: true,
      project_status_id: 1,
      business_function_id: 2,
      state_id: 3,
      product_category_id: 4,
    })
    const payload = buildUpdatePayload(values({ project_id: null }), linkedOriginal)

    expect(payload).toEqual({
      project_id: null,
      project_status_id: 1,
      business_function_id: 2,
      state_id: 3,
      product_category_id: 4,
    })
  })

  it('omits the 4 classification fields when the campaign stays linked, even if the diffed values differ', () => {
    const linkedOriginal = original({
      project_id: 5,
      derived_from_project: true,
      project_status_id: 9,
      business_function_id: 9,
      state_id: 9,
      product_category_id: 9,
    })
    const payload = buildUpdatePayload(values({ project_id: 5 }), linkedOriginal)

    expect(payload).not.toHaveProperty('project_status_id')
    expect(payload).not.toHaveProperty('business_function_id')
    expect(payload).not.toHaveProperty('state_id')
    expect(payload).not.toHaveProperty('product_category_id')
  })

  it('diffs the 4 classification fields normally when the campaign stays standalone', () => {
    const payload = buildUpdatePayload(values({ project_status_id: 8 }), original())
    expect(payload).toEqual({ project_status_id: 8 })
  })
})
