import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildDefaultStatusesPayload,
  buildUpdatePayload,
} from '@/features/opportunity-workflows/opportunity-workflow-form-payload'
import type { CreateOpportunityWorkflowFormValues } from '@/features/opportunity-workflows/opportunity-workflow-schema'
import type { WorkflowStatusFormRow } from '@/features/opportunity-workflows/types'

const values: CreateOpportunityWorkflowFormValues = {
  name: 'EMEA workflow',
  is_active: true,
  criteria: [
    { field: 'state_id', value_id: 1 },
    { field: 'source_id', value_id: 2 },
  ],
}

const CREATE_STATUS_ROWS: WorkflowStatusFormRow[] = [
  { id: 'system-open', name: 'Aperto', color: null, group: 'open', system_key: 'open' },
  { id: 'c1', name: 'In progress', color: 'blue', group: 'pending', system_key: null },
  { id: 'system-closed-won', name: 'Chiuso positivo', color: null, group: 'closed_won', system_key: 'closed_won' },
  { id: 'system-closed-lost', name: 'Chiuso negativo', color: null, group: 'closed_lost', system_key: 'closed_lost' },
]

const PERSISTED_STATUS_ROWS: WorkflowStatusFormRow[] = [
  { id: '1', statusId: 1, name: 'Open', color: null, group: 'open', system_key: 'open' },
  { id: '2', statusId: 2, name: 'In progress', color: 'blue', group: 'pending', system_key: null },
  { id: '3', name: 'New custom', color: 'green', group: 'pending', system_key: null },
  { id: '4', statusId: 4, name: 'Closed won', color: null, group: 'closed_won', system_key: 'closed_won' },
  { id: '5', statusId: 5, name: 'Closed lost', color: null, group: 'closed_lost', system_key: 'closed_lost' },
]

describe('buildCreatePayload', () => {
  it('builds the full create payload: pinned rows (tagged system_key) + custom rows, in order', () => {
    expect(buildCreatePayload(values, CREATE_STATUS_ROWS)).toEqual({
      name: 'EMEA workflow',
      is_active: true,
      criteria: [
        { field: 'state_id', value_id: 1 },
        { field: 'source_id', value_id: 2 },
      ],
      statuses: [
        { name: 'Aperto', color: null, group: 'open', system_key: 'open' },
        { name: 'In progress', color: 'blue', group: 'pending', system_key: null },
        { name: 'Chiuso positivo', color: null, group: 'closed_won', system_key: 'closed_won' },
        { name: 'Chiuso negativo', color: null, group: 'closed_lost', system_key: 'closed_lost' },
      ],
    })
  })

  it('drops an incomplete criteria row (field or value still unset)', () => {
    const incomplete: CreateOpportunityWorkflowFormValues = {
      ...values,
      criteria: [{ field: 'state_id', value_id: 1 }, { field: null, value_id: null }],
    }
    expect(buildCreatePayload(incomplete, []).criteria).toEqual([{ field: 'state_id', value_id: 1 }])
  })

  it('sends the pinned rows with their system_key and no id (nothing persisted yet)', () => {
    const payload = buildCreatePayload(values, CREATE_STATUS_ROWS)
    const systemKeys = payload.statuses?.map((status) => status.system_key)
    expect(systemKeys).toEqual(['open', null, 'closed_won', 'closed_lost'])
    expect(payload.statuses?.every((status) => !('id' in status))).toBe(true)
  })
})

describe('buildUpdatePayload', () => {
  it('sends the full authoritative criteria + statuses sync, including ids for persisted rows', () => {
    expect(buildUpdatePayload(values, PERSISTED_STATUS_ROWS)).toEqual({
      name: 'EMEA workflow',
      is_active: true,
      criteria: [
        { field: 'state_id', value_id: 1 },
        { field: 'source_id', value_id: 2 },
      ],
      statuses: [
        { id: 1, name: 'Open', color: null, group: 'open' },
        { id: 2, name: 'In progress', color: 'blue', group: 'pending' },
        { id: undefined, name: 'New custom', color: 'green', group: 'pending' },
        { id: 4, name: 'Closed won', color: null, group: 'closed_won' },
        { id: 5, name: 'Closed lost', color: null, group: 'closed_lost' },
      ],
    })
  })
})

describe('buildDefaultStatusesPayload', () => {
  it('wraps the same statuses sync shape under `statuses`', () => {
    expect(buildDefaultStatusesPayload(PERSISTED_STATUS_ROWS).statuses).toHaveLength(5)
  })
})
