import { describe, expect, it } from 'vitest'
import type { ColumnState, GridApi } from 'ag-grid-community'
import i18n from '@/i18n'
import { buildExportGridState } from '@/features/exports/build-export-grid-state'
import type { TableColumn, TableRow } from '@/features/table/types'

const ACTIONS = '__actions'

/** Minimal `GridApi` stub exposing only what the builder reads. */
function stubGridApi(
  columnState: ColumnState[],
  filterModel: Record<string, unknown> = {},
): GridApi<TableRow> {
  return {
    getColumnState: () => columnState,
    getFilterModel: () => filterModel,
  } as unknown as GridApi<TableRow>
}

function column(partial: Partial<TableColumn> & { id: string; label: string }): TableColumn {
  return {
    type: 'text',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: true,
    ...partial,
  }
}

describe('buildExportGridState', () => {
  it('exports visible columns in grid order with the resolved i18n header', async () => {
    await i18n.changeLanguage('en')
    const columnState: ColumnState[] = [
      { colId: 'name', hide: false } as ColumnState,
      { colId: 'created_at', hide: false } as ColumnState,
    ]
    const columns = [
      column({ id: 'name', label: 'companies.columns.denomination' }),
      column({ id: 'created_at', label: 'table.actionsHeader' }),
    ]

    const result = buildExportGridState({
      gridApi: stubGridApi(columnState),
      columns,
      actionsColumnId: ACTIONS,
      search: '',
      t: i18n.t,
    })

    expect(result.columns).toEqual([
      { colId: 'name', header: 'Denomination' },
      { colId: 'created_at', header: 'Actions' },
    ])
  })

  it('skips hidden columns and the synthetic row-actions column', () => {
    const columnState: ColumnState[] = [
      { colId: 'name', hide: false } as ColumnState,
      { colId: 'email', hide: true } as ColumnState,
      { colId: ACTIONS, hide: false } as ColumnState,
    ]
    const columns = [
      column({ id: 'name', label: 'companies.columns.denomination' }),
      column({ id: 'email', label: 'companies.columns.city' }),
    ]

    const result = buildExportGridState({
      gridApi: stubGridApi(columnState),
      columns,
      actionsColumnId: ACTIONS,
      search: '',
      t: i18n.t,
    })

    expect(result.columns.map((c) => c.colId)).toEqual(['name'])
  })

  it('falls back to the raw colId when no label is declared for it', () => {
    const columnState: ColumnState[] = [{ colId: 'unknown_col', hide: false } as ColumnState]

    const result = buildExportGridState({
      gridApi: stubGridApi(columnState),
      columns: [],
      actionsColumnId: ACTIONS,
      search: '',
      t: i18n.t,
    })

    expect(result.columns).toEqual([{ colId: 'unknown_col', header: 'unknown_col' }])
  })

  it('derives the sort model from column state, ordered by sortIndex', () => {
    const columnState: ColumnState[] = [
      { colId: 'name', hide: false, sort: 'asc', sortIndex: 1 } as ColumnState,
      { colId: 'created_at', hide: false, sort: 'desc', sortIndex: 0 } as ColumnState,
      { colId: 'email', hide: false, sort: null } as ColumnState,
    ]

    const result = buildExportGridState({
      gridApi: stubGridApi(columnState),
      columns: [],
      actionsColumnId: ACTIONS,
      search: '',
      t: i18n.t,
    })

    expect(result.sortModel).toEqual([
      { colId: 'created_at', sort: 'desc' },
      { colId: 'name', sort: 'asc' },
    ])
  })

  it('passes through the active filter model and the trimmed search term', () => {
    const filterModel = { name: { type: 'contains', filter: 'acme' } }

    const result = buildExportGridState({
      gridApi: stubGridApi([], filterModel),
      columns: [],
      actionsColumnId: ACTIONS,
      search: '  acme  ',
      t: i18n.t,
    })

    expect(result.filterModel).toBe(filterModel)
    expect(result.search).toBe('acme')
  })
})
