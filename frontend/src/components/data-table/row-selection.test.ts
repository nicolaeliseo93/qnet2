import { describe, expect, it } from 'vitest'
import type { IRowNode } from 'ag-grid-community'
import { buildRowSelectionOptions } from '@/components/data-table/row-selection'
import type { TableRow } from '@/features/table/types'

/**
 * `buildRowSelectionOptions` (spec 0048 AC-040) is exported as a pure
 * function precisely so its branching is testable without mounting
 * `AgGridReact` — see `data-table.tsx`'s `rowSelection` `useMemo`.
 */

function node(data: TableRow | undefined): IRowNode<TableRow> {
  return { data } as IRowNode<TableRow>
}

describe('buildRowSelectionOptions', () => {
  it('omits isRowSelectable when no predicate is supplied (every row stays selectable)', () => {
    const options = buildRowSelectionOptions()
    expect(options.isRowSelectable).toBeUndefined()
    expect(options.mode).toBe('multiRow')
  })

  it('wraps the row-data predicate into a node-based isRowSelectable', () => {
    const options = buildRowSelectionOptions((row) => row.lead_status === 'not_associated')

    expect(
      options.isRowSelectable?.(node({ id: 1, actions: [], lead_status: 'not_associated' })),
    ).toBe(true)
    expect(options.isRowSelectable?.(node({ id: 2, actions: [], lead_status: 'associated' }))).toBe(
      false,
    )
    expect(
      options.isRowSelectable?.(node({ id: 3, actions: [], lead_status: 'converted_to_opportunity' })),
    ).toBe(false)
  })

  it('treats a loading row (no data yet) as never selectable', () => {
    const options = buildRowSelectionOptions(() => true)
    expect(options.isRowSelectable?.(node(undefined))).toBe(false)
  })

  it('keeps the base config (multi-row checkboxes, current-page select-all) unchanged', () => {
    const base = buildRowSelectionOptions()
    const scoped = buildRowSelectionOptions(() => true)
    expect(scoped.checkboxes).toBe(base.checkboxes)
    expect(scoped.headerCheckbox).toBe(base.headerCheckbox)
    expect(scoped.selectAll).toBe(base.selectAll)
  })
})
