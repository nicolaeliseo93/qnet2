import type { ColumnState } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import { toColumnPreferences } from '@/features/table/use-table-preferences'

/**
 * Build a minimal AG Grid ColumnState. Only the fields the mapper reads
 * (colId/hide/width) matter; the rest of the type is irrelevant here.
 */
function columnState(partial: Partial<ColumnState> & { colId: string }): ColumnState {
  return partial as ColumnState
}

const ACTIONS = '__actions'

describe('toColumnPreferences', () => {
  it('maps display order, visibility and width from the grid state', () => {
    const state = [
      columnState({ colId: 'email', hide: false, width: 300 }),
      columnState({ colId: 'name', hide: true, width: 150 }),
    ]

    expect(toColumnPreferences(state, ACTIONS)).toEqual([
      { id: 'email', visible: true, order: 0, width: 300 },
      { id: 'name', visible: false, order: 1, width: 150 },
    ])
  })

  it('excludes the synthetic row-actions column', () => {
    const state = [
      columnState({ colId: 'name', hide: false, width: 200 }),
      columnState({ colId: ACTIONS, hide: false, width: 80 }),
    ]

    const result = toColumnPreferences(state, ACTIONS)

    expect(result.map((column) => column.id)).toEqual(['name'])
  })

  it('omits width when it is not a number (never sends null)', () => {
    const state = [
      columnState({ colId: 'name', hide: false, width: null }),
      columnState({ colId: 'email', hide: false }),
    ]

    const result = toColumnPreferences(state, ACTIONS)

    expect(result[0]).not.toHaveProperty('width')
    expect(result[1]).not.toHaveProperty('width')
    expect(result).toEqual([
      { id: 'name', visible: true, order: 0 },
      { id: 'email', visible: true, order: 1 },
    ])
  })

  it('re-derives order from the array position after a reorder', () => {
    const state = [
      columnState({ colId: 'created_at', hide: false }),
      columnState({ colId: 'id', hide: false }),
      columnState({ colId: 'name', hide: false }),
    ]

    expect(toColumnPreferences(state, ACTIONS).map((c) => c.order)).toEqual([
      0, 1, 2,
    ])
  })
})
