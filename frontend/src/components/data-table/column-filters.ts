/**
 * Column -> AG Grid filter resolution for the generic DataTable (0004: Excel-like
 * filters). Pure logic, no JSX, so it can export plain functions alongside the
 * component-only `data-table.tsx` without tripping react-refresh.
 */
import type {
  ColDef,
  IMultiFilterParams,
  ISetFilterParams,
  SetFilterValuesFunc,
  SetFilterValuesFuncParams,
} from 'ag-grid-community'
import { fetchTableColumnValues } from '@/features/table/api'
import type { TableColumn } from '@/features/table/types'

/**
 * Maps a backend column to the AG Grid filter component, or false.
 *
 * Prefers the explicit `filterType` from the config contract (0002) when
 * present, falling back to the column `type` for backward compatibility.
 * set/enum/tags/badge/boolean columns get the standalone `agSetColumnFilter`
 * (0004). text/number/date(-time) columns get the combined `agMultiColumnFilter`
 * (Set Filter + a typed condition, see `resolveTypedFilter`) UNLESS the column
 * is computed/derived without a queryable value list (`hasFilterValues ===
 * false`, 0005 — e.g. a concatenated address), in which case they fall back to
 * the plain typed condition filter alone, with no Set tab.
 */
export function resolveFilter(column: TableColumn): ColDef['filter'] {
  if (!column.filterable) {
    return false
  }
  const kind = column.filterType ?? column.type
  switch (kind) {
    case 'set':
    case 'enum':
    case 'tags':
    case 'badge':
    case 'boolean':
      return 'agSetColumnFilter'
    default:
      return column.hasFilterValues === false
        ? resolveTypedFilter(column)
        : 'agMultiColumnFilter'
  }
}

/**
 * The typed condition filter for text/number/date columns: stacked alongside
 * the Set Filter inside an `agMultiColumnFilter`, or used standalone for
 * computed columns that have no Set value list (`hasFilterValues === false`).
 */
export function resolveTypedFilter(
  column: TableColumn,
): 'agNumberColumnFilter' | 'agDateColumnFilter' | 'agTextColumnFilter' {
  const kind = column.filterType ?? column.type
  switch (kind) {
    case 'number':
      return 'agNumberColumnFilter'
    case 'date':
    case 'datetime':
      return 'agDateColumnFilter'
    default:
      return 'agTextColumnFilter'
  }
}

/**
 * Builds the Set Filter's async `values` callback for one column: fetches the
 * distinct values from POST /tables/{domain}/values, scoped to the filters
 * currently active on the OTHER columns — the column never self-restricts its
 * own list (Excel behavior, 0004). Reads the live filter model off the grid
 * API rather than a snapshot, so it always reflects the state at the moment
 * the filter is opened (paired with `refreshValuesOnOpen`). A fetch failure
 * resolves to an empty list so the filter UI never crashes.
 */
export function createColumnValuesGetter(
  domain: string,
  columnId: string,
  onTruncated: () => void,
): SetFilterValuesFunc {
  return (params: SetFilterValuesFuncParams) => {
    const filterModel: Record<string, unknown> = { ...params.api.getFilterModel() }
    delete filterModel[columnId]

    fetchTableColumnValues(domain, { columnId, filterModel })
      .then((response) => {
        if (response.hasMore) {
          onTruncated()
        }
        params.success(response.values)
      })
      .catch(() => params.success([]))
  }
}

/**
 * Set Filter params shared by both the standalone Set Filter and the one
 * inlined inside `agMultiColumnFilter`. `excelMode: 'windows'` gives it the
 * classic Excel checklist chrome (search + Select All + Apply/Reset), so
 * every filterable column reads the same regardless of widget (0005).
 * `refreshValuesOnOpen` re-fetches every time the popup opens (the OTHER
 * columns' filters may have changed since); `suppressClearModelOnRefreshValues`
 * keeps the current selection even if a refreshed value list temporarily
 * doesn't include it yet — recommended by AG Grid for SSRM setups that derive
 * one column's values from the others.
 */
export function buildSetFilterParams(
  domain: string,
  columnId: string,
  onTruncated: () => void,
): ISetFilterParams {
  return {
    values: createColumnValuesGetter(domain, columnId, onTruncated),
    refreshValuesOnOpen: true,
    suppressClearModelOnRefreshValues: true,
    excelMode: 'windows',
  }
}

/** i18n key for the sub-menu title of the typed condition filter (0005). */
const TYPED_FILTER_TITLE_KEY: Record<
  ReturnType<typeof resolveTypedFilter>,
  string
> = {
  agTextColumnFilter: 'table.textFilters',
  agNumberColumnFilter: 'table.numberFilters',
  agDateColumnFilter: 'table.dateFilters',
}

/**
 * Full `filter`/`filterParams` pair for a column's AG Grid `ColDef`.
 *
 * Excel-classic layout (0005): a multi-filter column shows the Set Filter
 * checklist INLINE as the primary view, with the typed condition filter
 * tucked into a titled sub-menu — one consistent panel across every
 * filterable column, not a tab switch. Standalone Set Filter columns
 * (set/enum/tags/badge/boolean) get the same checklist chrome directly. A
 * plain typed condition filter (computed columns, `hasFilterValues === false`)
 * and non-filterable columns fall through with no params — no Set Filter, so
 * no values-callback is ever built for them.
 */
export function buildColumnFilter(
  domain: string,
  column: TableColumn,
  onTruncated: () => void,
  translate: (key: string) => string,
): { filter: ColDef['filter']; filterParams: ColDef['filterParams'] } {
  const filter = resolveFilter(column)
  if (filter === 'agSetColumnFilter') {
    return { filter, filterParams: buildSetFilterParams(domain, column.id, onTruncated) }
  }
  if (filter === 'agMultiColumnFilter') {
    const typedFilter = resolveTypedFilter(column)
    const filterParams: IMultiFilterParams = {
      filters: [
        {
          filter: 'agSetColumnFilter',
          filterParams: buildSetFilterParams(domain, column.id, onTruncated),
        },
        {
          filter: typedFilter,
          display: 'subMenu',
          title: translate(TYPED_FILTER_TITLE_KEY[typedFilter]),
        },
      ],
    }
    return { filter, filterParams }
  }
  return { filter, filterParams: undefined }
}
