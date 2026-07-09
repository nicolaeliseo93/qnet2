import { SELECTION_COLUMN_ID, type ColumnState } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import { toColumnPreferences } from '@/features/table/use-table-preferences'

/**
 * Build a minimal AG Grid ColumnState. Only the fields the mapper reads
 * (colId/hide/width) matter; the rest of the type is irrelevant here.
 */
function columnState(partial: Partial<ColumnState> & { colId: string }): ColumnState {
  return partial as ColumnState
}

const SELECTION = 'ag-Grid-SelectionColumn'
const ACTIONS = '__actions'

describe('toColumnPreferences', () => {
  it('maps display order, visibility and width from the grid state', () => {
    const state = [
      columnState({ colId: 'email', hide: false, width: 300 }),
      columnState({ colId: 'name', hide: true, width: 150 }),
    ]

    expect(toColumnPreferences(state, new Set(['email', 'name']))).toEqual([
      { id: 'email', visible: true, order: 0, width: 300 },
      { id: 'name', visible: false, order: 1, width: 150 },
    ])
  })

  it('excludes synthetic grid columns absent from the allow-list', () => {
    // Regression: on tables with row selection AG Grid injects a real
    // 'ag-Grid-SelectionColumn' into getColumnState(); it is not a declared
    // column, so sending it 422s the whole save (Rule::in) and nothing persists.
    const state = [
      columnState({ colId: SELECTION, hide: false, width: 50 }),
      columnState({ colId: 'name', hide: false, width: 200 }),
      columnState({ colId: ACTIONS, hide: false, width: 80 }),
    ]

    const result = toColumnPreferences(state, new Set(['name', 'email']))

    expect(result.map((column) => column.id)).toEqual(['name'])
  })

  it('excludes AG Grid synthetic selection column, keeping order 0-based', () => {
    const state = [
      columnState({ colId: SELECTION_COLUMN_ID, hide: false }),
      columnState({ colId: 'name', hide: false }),
      columnState({ colId: 'email', hide: false }),
    ]

    const result = toColumnPreferences(state, ACTIONS)

    expect(result).toEqual([
      { id: 'name', visible: true, order: 0 },
      { id: 'email', visible: true, order: 1 },
    ])
  })

  it('omits width when it is not a number (never sends null)', () => {
    const state = [
      columnState({ colId: 'name', hide: false, width: null }),
      columnState({ colId: 'email', hide: false }),
    ]

    const result = toColumnPreferences(state, new Set(['name', 'email']))

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

    const known = new Set(['created_at', 'id', 'name'])

    expect(toColumnPreferences(state, known).map((c) => c.order)).toEqual([
      0, 1, 2,
    ])
  })
})
