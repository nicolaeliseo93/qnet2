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
    client_identity: null,
    client_contacts: { owner: { type: 'personal_data', id: 10 }, items: [] },
    client_address: null,
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
    next_callback_at: null,
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    ...overrides,
  }
}

function formValues(overrides: Partial<RequestWorkFormValues> = {}): RequestWorkFormValues {
  return {
    opportunity_workflow_status_id: 100,
    next_callback_at: null,
    note: '',
    client_identity: null,
    client_contacts: [],
    client_address: [],
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

  it('sends the whole client_contacts set when one channel was typed in', () => {
    const payload = buildRequestWorkPayload(
      formValues({
        client_contacts: [
          { _key: 'c1', type: 'email', value: 'ops@acme.test', label: null, is_primary: true },
        ],
      }),
      panel(),
    )
    expect(payload).toEqual({
      client_contacts: [{ type: 'email', value: 'ops@acme.test', label: null, is_primary: true }],
    })
  })

  it('keeps the loaded client contacts out of the payload when untouched', () => {
    const loaded = { id: 7, type: 'email', value: 'ops@acme.test', label: null, is_primary: true }
    const payload = buildRequestWorkPayload(
      formValues({ client_contacts: [{ ...loaded, _key: 'contact-7' }] }),
      panel({ client_contacts: { owner: { type: 'personal_data', id: 10 }, items: [loaded] } }),
    )
    expect(payload).toEqual({})
  })

  it('sends client_address with its id when the loaded address was edited', () => {
    const loaded = {
      id: 3,
      line1: 'Via Vecchia 1',
      line2: null,
      postal_code: '20100',
      city_id: 5,
      province_id: null,
      state_id: null,
      country_id: 1,
    }
    const payload = buildRequestWorkPayload(
      formValues({
        client_address: [
          { ...loaded, _key: 'address-3', line1: 'Via Nuova 2', is_primary: true, site_type: 'billing' },
        ],
      }),
      panel({ client_address: { ...loaded, is_primary: true } as RequestWorkPanel['client_address'] }),
    )
    expect(payload).toEqual({ client_address: { ...loaded, line1: 'Via Nuova 2' } })
  })

  it('omits client_address entirely when the inline fields were left blank', () => {
    expect(buildRequestWorkPayload(formValues({ client_address: [] }), panel())).toEqual({})
  })

  it('treats null and an absent original value as equal (no spurious diff)', () => {
    const payload = buildRequestWorkPayload(
      formValues(),
      panel({ attribute_values: { notes: 'existing note', budget: 1000, extra: undefined } }),
    )
    expect(payload).toEqual({})
  })
})

describe('buildRequestWorkPayload — note on a requires_note status change (spec 0054 D-5)', () => {
  const requiresNotePanel = panel({
    workflow_statuses: [
      { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
      { id: 101, name: 'In progress', color: 'amber', system_key: null, description: null, requires_note: true },
    ],
  })

  it('sends the note alongside the status when the target status requires one', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 101, note: 'Client confirmed by phone.' }),
      requiresNotePanel,
    )
    expect(payload).toEqual({ opportunity_workflow_status_id: 101, note: 'Client confirmed by phone.' })
  })

  it('omits the note when the target status does not require one', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 100, note: 'Ignored, status is unchanged anyway.' }),
      requiresNotePanel,
    )
    expect(payload).toEqual({})
  })

  it('omits a blank note even when the target status requires one', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 101, note: '   ' }),
      requiresNotePanel,
    )
    expect(payload).toEqual({ opportunity_workflow_status_id: 101 })
  })

  it('never sends the note when the status did not change', () => {
    const payload = buildRequestWorkPayload(
      formValues({ opportunity_workflow_status_id: 100, note: 'Stray text left in the field.' }),
      requiresNotePanel,
    )
    expect(payload).toEqual({})
  })
})
