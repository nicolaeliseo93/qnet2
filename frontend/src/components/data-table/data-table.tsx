import { useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AgGridReact } from 'ag-grid-react'
import {
  themeQuartz,
  type ColDef,
  type ColumnMovedEvent,
  type ColumnResizedEvent,
  type ColumnVisibleEvent,
  type GridOptions,
  type GridReadyEvent,
  type ICellRendererParams,
  type IServerSideDatasource,
} from 'ag-grid-community'
import {
  AG_GRID_LOCALE_EN,
  AG_GRID_LOCALE_IT,
} from '@ag-grid-community/locale'
import { setupAgGrid } from '@/components/data-table/ag-grid-setup'
import { Skeleton } from '@/components/ui/skeleton'
import { BadgeCell } from '@/features/table/cell-renderers'
import type { ColumnType, TableColumn } from '@/features/table/types'

// Register enterprise modules + license once, at module load.
setupAgGrid()

/** Column id of the synthetic, right-pinned row-actions column. */
export const ACTIONS_COLUMN_ID = '__actions'

/**
 * Per-cell loading placeholder shown while an SSRM block streams in. Because AG
 * Grid renders it once per cell of every loading row, the skeleton naturally
 * follows the column layout (one bar per column) without us knowing the data.
 * The trailing actions column gets a narrower bar so the row reads as content.
 */
export function SkeletonLoadingCell({ colDef }: ICellRendererParams) {
  const width = colDef?.colId === ACTIONS_COLUMN_ID ? 'w-12' : 'w-[70%]'
  return (
    <div className="flex h-full items-center">
      <Skeleton className={`h-4 ${width}`} />
    </div>
  )
}

/** A custom cell renderer keyed by column id. Receives the AG Grid cell params. */
export type CellRenderer = (params: ICellRendererParams) => React.ReactNode

/** Renders the per-row actions cell (right-most column). */
export type RowActionsRenderer = (params: ICellRendererParams) => React.ReactNode

interface DataTableProps {
  /** Backend-driven column schema. */
  columns: TableColumn[]
  /** SSRM datasource feeding the grid. */
  datasource: IServerSideDatasource
  /** Default page/block size from the backend config. */
  blockSize: number
  /** Optional map columnId → custom cell renderer (badges, links, formatting). */
  cellRenderers?: Record<string, CellRenderer>
  /** Optional renderer for the trailing row-actions column. */
  renderRowActions?: RowActionsRenderer
  /** i18n key for the action column header. */
  actionsHeaderLabel?: string
  /**
   * Fired once the grid is ready. Exposes the AG Grid event so callers can keep
   * a reference to the grid API (e.g. to refresh SSRM blocks after a mutation).
   * The wrapper stays agnostic: it forwards the event without acting on it.
   */
  onGridReady?: (event: GridReadyEvent) => void
  /**
   * Fired (debounced by the caller) when the USER changes the column layout —
   * reorder, resize, or show/hide. Programmatic (`api`) and flex-driven changes
   * are filtered out here so they never trigger a persist loop. The wrapper does
   * not read or store the state; the caller pulls it from the grid API.
   */
  onColumnStateChanged?: () => void
}

/**
 * Maps a backend column to the AG Grid filter component, or false.
 *
 * Prefers the explicit `filterType` from the config contract (0002) when
 * present, falling back to the column `type` for backward compatibility.
 */
function resolveFilter(column: TableColumn): ColDef['filter'] {
  if (!column.filterable) {
    return false
  }
  if (column.filterType) {
    switch (column.filterType) {
      case 'number':
        return 'agNumberColumnFilter'
      case 'date':
        return 'agDateColumnFilter'
      case 'set':
        return 'agSetColumnFilter'
      case 'text':
      default:
        return 'agTextColumnFilter'
    }
  }
  switch (column.type) {
    case 'number':
      return 'agNumberColumnFilter'
    case 'datetime':
      return 'agDateColumnFilter'
    case 'tags':
    case 'enum':
    case 'badge':
      return 'agSetColumnFilter'
    case 'text':
    default:
      return 'agTextColumnFilter'
  }
}

/** Default cell value formatter for the given column type. */
function defaultValueFormatter(type: ColumnType) {
  if (type === 'tags') {
    return (value: unknown): string =>
      Array.isArray(value) ? value.join(', ') : String(value ?? '')
  }
  return undefined
}

/**
 * Backend-driven, reusable AG Grid wrapper.
 *
 * Stays agnostic: it translates the backend column schema into `ColDef[]`
 * (field, headerName, hide, sortable, filter) and lets callers override
 * rendering per column id and supply a row-actions renderer. It never embeds
 * domain logic or API calls — data arrives through the SSRM `datasource`.
 */
export function DataTable({
  columns,
  datasource,
  blockSize,
  cellRenderers,
  renderRowActions,
  actionsHeaderLabel,
  onGridReady,
  onColumnStateChanged,
}: DataTableProps) {
  const { t, i18n } = useTranslation()

  // AG Grid's own UI strings (filter menus, set filter, column panel, context
  // menu, pagination, "Loading…"/"No Rows To Show") come from the official
  // @ag-grid-community/locale package, selected by the active app language and
  // recomputed on language change. English is the fallback for any other locale.
  const localeText = useMemo(
    () => (i18n.language.startsWith('it') ? AG_GRID_LOCALE_IT : AG_GRID_LOCALE_EN),
    [i18n.language],
  )

  const colDefs = useMemo<ColDef[]>(() => {
    const mapped: ColDef[] = columns.map((column) => {
      const custom = cellRenderers?.[column.id]
      // Generic, domain-agnostic fallback for `badge` columns: the cell is driven
      // entirely by the backend-supplied badge metadata, so no domain has to
      // register a renderer for it.
      const badgeFallback =
        !custom && column.type === 'badge'
          ? (params: ICellRendererParams) => (
              <BadgeCell {...params} badges={column.badges} enumKey={column.enumKey} />
            )
          : undefined
      const renderer = custom ?? badgeFallback
      const filterWidget = resolveFilter(column)
      // A column with a persisted width uses it as a fixed width (flex:0 opts it
      // out of the flex layout); columns without one keep flexing to fill space
      // via defaultColDef.flex. Columns arrive already ordered by `order`.
      const hasWidth = column.width != null
      return {
        colId: column.id,
        field: column.id,
        headerName: t(column.label),
        hide: !column.visible,
        width: hasWidth ? column.width! : undefined,
        flex: hasWidth ? 0 : undefined,
        sortable: column.sortable,
        filter: filterWidget,
        // Set filters need their values supplied by the backend, not derived
        // from the (paged) client rows. Keyed off the resolved widget so it also
        // covers text/badge-rendered columns that carry a `set` filterType.
        filterParams:
          filterWidget === 'agSetColumnFilter'
            ? { values: column.options ?? [] }
            : undefined,
        cellRenderer: renderer
          ? (params: ICellRendererParams) => renderer(params)
          : undefined,
        valueFormatter: renderer
          ? undefined
          : defaultValueFormatter(column.type)
            ? (params) => defaultValueFormatter(column.type)!(params.value)
            : undefined,
      }
    })

    if (renderRowActions) {
      mapped.push({
        colId: ACTIONS_COLUMN_ID,
        headerName: actionsHeaderLabel ? t(actionsHeaderLabel) : '',
        sortable: false,
        filter: false,
        resizable: false,
        pinned: 'right',
        cellRenderer: (params: ICellRendererParams) => renderRowActions(params),
      })
    }

    return mapped
  }, [columns, cellRenderers, renderRowActions, actionsHeaderLabel, t])

  // Persist only USER-driven layout changes. AG Grid also emits these events for
  // its own programmatic updates (`api`, e.g. when we apply new columnDefs) and
  // for flex re-layout (`flex`); excluding them prevents a save↔rebuild loop.
  // Resize/move are debounced upstream and only acted on when `finished`.
  const handleColumnResized = useCallback(
    (event: ColumnResizedEvent) => {
      if (event.finished && event.source !== 'api' && event.source !== 'flex') {
        onColumnStateChanged?.()
      }
    },
    [onColumnStateChanged],
  )
  const handleColumnMoved = useCallback(
    (event: ColumnMovedEvent) => {
      if (event.finished && event.source !== 'api') {
        onColumnStateChanged?.()
      }
    },
    [onColumnStateChanged],
  )
  const handleColumnVisible = useCallback(
    (event: ColumnVisibleEvent) => {
      if (event.source !== 'api') {
        onColumnStateChanged?.()
      }
    },
    [onColumnStateChanged],
  )

  const gridOptions = useMemo<GridOptions>(
    () => ({
      rowModelType: 'serverSide',
      serverSideDatasource: datasource,
      cacheBlockSize: blockSize,
      // Show a column-shaped skeleton while each SSRM block loads: opting out of
      // the full-width loading row makes AG Grid render `loadingCellRenderer`
      // per cell, so the placeholder mirrors the real column layout.
      suppressServerSideFullWidthLoadingRow: true,
      loadingCellRenderer: SkeletonLoadingCell,
      // Render a full page of loading rows on the initial load too: without this,
      // SSRM shows a single loading row until the first response reveals the row
      // count. Seeding the count with the page size makes the grid paint
      // `blockSize` skeleton rows immediately.
      serverSideInitialRowCount: blockSize,
      pagination: true,
      paginationPageSize: blockSize,
      paginationPageSizeSelector: [blockSize, blockSize * 2, blockSize * 4],
      defaultColDef: {
        resizable: true,
        flex: 1,
        minWidth: 120,
      },
    }),
    [datasource, blockSize],
  )

  return (
    <div className="h-[600px] w-full">
      <AgGridReact
        theme={themeQuartz}
        columnDefs={colDefs}
        localeText={localeText}
        onGridReady={onGridReady}
        onColumnResized={handleColumnResized}
        onColumnMoved={handleColumnMoved}
        onColumnVisible={handleColumnVisible}
        {...gridOptions}
      />
    </div>
  )
}
