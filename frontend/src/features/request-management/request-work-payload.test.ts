import { describe, expect, it } from 'vitest'
import { buildRequestWorkPayload } from '@/features/request-management/request-work-payload'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestWorkPanel } from '@/features/request-management/types'

/** Spec 0049 AC-062: the PATCH payload is sparse — only what actually changed. */

function panel(overrides: Partial<RequestWorkPanel> = {}): RequestWorkPanel {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: { id: 20, name: 'Mario Rossi' },
    commercial: null,
    opportunity_status: { id: 5, name: 'New', color: 'slate' },
    workflow_status: { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
    workflow_statuses: [
      { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
      { id: 101, name: 'In progress', color: 'amber', system_key: null, description: null, requires_note: false },
    ],
    product_lines: [],
    client_contacts: { owner: { type: 'personal_data', id: 10 }, items: [] },
    referent_contacts: { owner: { type: 'personal_data', id: 20 }, items: [] },
    applicable_attributes: [
      {
        id: 1,
        code: 'notes',
        name: 'Notes',
        type: 'text',
        description: null,
        help_text: null,
        placeholder: null,
        icon: null,
        config: null,
        relation_target: null,
        is_required: false,
        sort_order: 1,
        options: [],
      },
      {
        id: 2,
        code: 'budget',
        name: 'Budget',
        type: 'integer',
        description: null,
        help_text: null,
        placeholder: null,
        icon: null,
        config: null,
        relation_target: null,
        is_required: false,
        sort_order: 2,
        options: [],
      },
    ],
    attribute_values: { notes: 'existing note', budget: 1000 },
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    ...overrides,
  }
}

function formValues(overrides: Partial<RequestWorkFormValues> = {}): RequestWorkFormValues {
  return {
    opportunity_workflow_status_id: 100,
    attribute_values: { notes: 'existing note', budget: 1000 },
    ...overrides,
  }
}

describe('buildRequestWorkPayload (spec 0049 AC-062)', () => {
  it('returns an empty payload when nothing changed', () => {
    expect(buildRequestWorkPayload(formValues(), panel())).toEqual({})
  })

  it('sends only the working status when just that changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 101 }),
      panel(),
    )
    expect(payload).toEqual({ opportunity_workflow_status_id: 101 })
  })

  it('sends the whole attribute_values map when at least one value changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ attribute_values: { notes: 'updated note', budget: 1000 } }),
      panel(),
    )
    expect(payload).toEqual({ attribute_values: { notes: 'updated note', budget: 1000 } })
  })

  it('sends both keys when the working status and an attribute value both changed', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 101, attribute_values: { notes: 'new', budget: 1000 } }),
      panel(),
    )
    expect(payload).toEqual({
      opportunity_workflow_status_id: 101,
      attribute_values: { notes: 'new', budget: 1000 },
    })
  })

  it('treats null and an absent original value as equal (no spurious diff)', () => {
    const payload = buildRequestWorkPayload(
      formValues(),
      panel({ attribute_values: { notes: 'existing note', budget: 1000, extra: undefined } }),
    )
    expect(payload).toEqual({})
  })
})
