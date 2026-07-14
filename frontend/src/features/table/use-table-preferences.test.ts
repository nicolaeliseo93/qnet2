import { SELECTION_COLUMN_ID, type ColumnState } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import {
  MAX_COLUMN_WIDTH,
  MIN_COLUMN_WIDTH,
  toColumnPreferences,
} from '@/features/table/use-table-preferences'

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

    const result = toColumnPreferences(state, new Set(['name', 'email']))

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

  it('rounds a fractional dragged width to an integer', () => {
    // Regression: AG Grid computes a manual resize as `startWidth + pointer
    // delta`, unrounded — under browser zoom / HiDPI the width is fractional and
    // the server's `integer` rule 422s the whole payload, losing the layout.
    const state = [columnState({ colId: 'name', hide: false, width: 247.5 })]

    expect(toColumnPreferences(state, new Set(['name']))).toEqual([
      { id: 'name', visible: true, order: 0, width: 248 },
    ])
  })

  it('clamps a width to the range the server accepts', () => {
    // Same failure mode: AG Grid sets no maxWidth of its own, so a wide drag
    // exceeds the server's max:1000 and 422s every column with it.
    const state = [
      columnState({ colId: 'name', hide: false, width: 1400 }),
      columnState({ colId: 'email', hide: false, width: 12 }),
    ]

    expect(toColumnPreferences(state, new Set(['name', 'email']))).toEqual([
      { id: 'name', visible: true, order: 0, width: MAX_COLUMN_WIDTH },
      { id: 'email', visible: true, order: 1, width: MIN_COLUMN_WIDTH },
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
