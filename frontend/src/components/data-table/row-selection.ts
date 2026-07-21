import type { IRowNode, RowSelectionOptions } from 'ag-grid-community'
import type { TableRow } from '@/features/table/types'

/**
 * Row-selection config shared by every domain that opts in (`enableSelection`).
 * Multi-row checkboxes with a header "select all"; `selectAll: 'currentPage'`
 * caps select-all at the loaded page instead of every row across the SSRM
 * dataset, matching the bulk-delete contract (explicit ids only, no
 * server-side "select all" semantics).
 */
const ROW_SELECTION = {
  mode: 'multiRow',
  checkboxes: true,
  headerCheckbox: true,
  selectAll: 'currentPage',
} satisfies RowSelectionOptions<TableRow>

/**
 * Builds the grid's row-selection config, optionally wrapping a domain's
 * row-data predicate into AG Grid's node-based `isRowSelectable` (generic,
 * opt-in capability: a domain can make some rows non-selectable). A loading
 * row has no `data` yet and is never selectable. Omitted `isRowSelectable`
 * returns the base config unchanged — every row stays selectable (the default
 * for every current domain). Exported (pure, no grid instance needed) so its
 * branching is unit-testable without mounting `AgGridReact`.
 */
export function buildRowSelectionOptions(
  isRowSelectable?: (row: TableRow) => boolean,
): typeof ROW_SELECTION & { isRowSelectable?: (node: IRowNode<TableRow>) => boolean } {
  if (!isRowSelectable) {
    return ROW_SELECTION
  }
  return {
    ...ROW_SELECTION,
    isRowSelectable: (node: IRowNode<TableRow>) => Boolean(node.data && isRowSelectable(node.data)),
  }
}
