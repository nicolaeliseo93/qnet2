import { useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AgGridReact } from 'ag-grid-react'
import {
  iconOverrides,
  themeQuartz,
  type ColDef,
  type ColumnMovedEvent,
  type ColumnResizedEvent,
  type ColumnVisibleEvent,
  type GridOptions,
  type GridReadyEvent,
  type GridState,
  type ICellRendererParams,
  type IServerSideDatasource,
  type ModelUpdatedEvent,
} from 'ag-grid-community'
import {
  AG_GRID_LOCALE_EN,
  AG_GRID_LOCALE_IT,
} from '@ag-grid-community/locale'
import { toast } from 'sonner'
import { setupAgGrid } from '@/components/data-table/ag-grid-setup'
import { buildColumnFilter } from '@/components/data-table/column-filters'
import { Skeleton } from '@/components/ui/skeleton'
import { BadgeCell } from '@/features/table/cell-renderers'
import type { ColumnType, TableColumn } from '@/features/table/types'

// Register enterprise modules + license once, at module load.
setupAgGrid()

/**
 * Funnel glyph for the column header filter button. The stock Quartz `filter`
 * icon is three stacked lines, not a funnel; this replaces it with a funnel
 * outline in the app's lucide icon language. The stroke color is irrelevant —
 * `mask: true` uses only the shape's alpha, so the button keeps the theme's
 * icon color (and tracks dark mode) automatically.
 */
const FILTER_FUNNEL_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>'

/**
 * Compact grid theme aligned to the app's design tokens. The grid sits on the
 * white `--card` surface so it stands out against the grey `--background` body,
 * while borders/hover/header text reference the shared CSS variables
 * (`--border`, `--muted*`) so it tracks the app palette and dark mode
 * automatically. Sizing is tightened (smaller rows, smaller font) and
 * cells/headers carry light borders in the app border color.
 */
const dataTableTheme = themeQuartz
  .withParams({
    fontFamily: 'inherit',
    fontSize: 12,
    rowHeight: 28,
    headerHeight: 32,
    headerFontSize: 10,
    headerFontWeight: 600,
    cellHorizontalPadding: 10,
    backgroundColor: 'var(--card)',
    foregroundColor: 'var(--card-foreground)',
    borderColor: 'var(--border)',
    headerBackgroundColor: 'var(--card)',
    headerTextColor: 'var(--muted-foreground)',
    rowHoverColor: 'var(--muted)',
    // The grid is fused into the toolbar block (spec 0009): the outer card owns
    // the border and radius, so the grid drops its own wrapper border to read as
    // one continuous surface with the header above it.
    wrapperBorder: false,
    rowBorder: true,
    columnBorder: true,
    headerColumnBorder: true,
    wrapperBorderRadius: 0,
  })
  // Swap the header filter button's three-line glyph for an actual funnel.
  .withPart(
    iconOverrides({
      type: 'image',
      mask: true,
      icons: { filter: { svg: FILTER_FUNNEL_SVG } },
    }),
  )

/** Column id of the synthetic, right-pinned row-actions column. */
export const ACTIONS_COLUMN_ID = '__actions'

/** Default minimum width for data columns without an explicit backend width. */
const DEFAULT_MIN_WIDTH = 120

/**
 * Fixed width of the row-actions column. Sized to hold up to three compact icon
 * buttons; beyond that the actions collapse into a single overflow menu, so the
 * column never needs to grow. Kept narrow because it only carries controls.
 */
const ACTIONS_COLUMN_WIDTH = 100

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
  /**
   * Domain key selecting the server-side table definition (e.g. "users").
   * The only domain-specific input the wrapper needs: it feeds the Set
   * Filter's async values-callback (POST /tables/{domain}/values), nothing
   * else about the domain leaks in.
   */
  domain: string
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
  /**
   * The user's saved filterModel, applied once at grid creation so the filters
   * (and the first SSRM request) reflect the persisted state on mount. Passed
   * through `initialState` — a later value has no effect without a remount, which
   * is exactly how the caller drives a filter reset (bump the grid `key`).
   */
  initialFilterModel?: Record<string, unknown>
  /**
   * Fired when the user changes a column filter. The wrapper does not read or
   * store the filter model; the caller pulls it from the grid API (debounced) to
   * persist it. Forwarded verbatim from AG Grid's `onFilterChanged`.
   */
  onFilterChanged?: () => void
  /**
   * Fired with the grid's total known row count whenever the model updates, so
   * the toolbar can show a live "N rows" counter (spec 0009).
   */
  onRowCountChanged?: (count: number) => void
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
  domain,
  columns,
  datasource,
  blockSize,
  cellRenderers,
  renderRowActions,
  actionsHeaderLabel,
  onGridReady,
  onColumnStateChanged,
  initialFilterModel,
  onFilterChanged,
  onRowCountChanged,
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
      // A column with a persisted width uses it as a fixed width (flex:0 opts it
      // out of the flex layout); columns without one keep flexing to fill space
      // via defaultColDef.flex. Columns arrive already ordered by `order`.
      const hasWidth = column.width != null
      // Every Set Filter (standalone or nested in the Multi Filter) gets its
      // values from the server, never a backend one-off list or the paged
      // client rows (0004) — see `buildColumnFilter`.
      const { filter, filterParams } = buildColumnFilter(
        domain,
        column,
        () => toast.info(t('table.filterValuesTruncated')),
        t,
      )
      return {
        colId: column.id,
        field: column.id,
        headerName: t(column.label),
        hide: !column.visible,
        width: hasWidth ? column.width! : undefined,
        // Let an intentionally-narrow backend width take effect: without this the
        // global DEFAULT_MIN_WIDTH would clamp it (e.g. the small avatar column).
        minWidth: hasWidth ? Math.min(DEFAULT_MIN_WIDTH, column.width!) : undefined,
        flex: hasWidth ? 0 : undefined,
        sortable: column.sortable,
        filter,
        filterParams,
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
        width: ACTIONS_COLUMN_WIDTH,
        minWidth: ACTIONS_COLUMN_WIDTH,
        flex: 0,
        cellRenderer: (params: ICellRendererParams) => renderRowActions(params),
      })
    }

    return mapped
  }, [domain, columns, cellRenderers, renderRowActions, actionsHeaderLabel, t])

  // Persist only USER-driven layout changes. AG Grid also emits these events for
  // its own programmatic updates (`api`, e.g. when we apply new columnDefs) and
  // for flex re-layout (`flex`); excluding them prevents a save-then-rebuild loop.
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

  // Report the grid's total known row count on every model update. For the SSRM
  // this is the `rowCount` the datasource last reported (the query total), which
  // is exactly the "N rows" figure the toolbar shows.
  const handleModelUpdated = useCallback(
    (event: ModelUpdatedEvent) => {
      onRowCountChanged?.(event.api.getDisplayedRowCount())
    },
    [onRowCountChanged],
  )

  // Apply the saved filters once, at grid creation, so the first SSRM request is
  // already filtered. Omitted when empty so a clean table starts unfiltered.
  const initialState = useMemo<GridState | undefined>(
    () =>
      initialFilterModel && Object.keys(initialFilterModel).length > 0
        ? { filter: { filterModel: initialFilterModel } }
        : undefined,
    [initialFilterModel],
  )

  const gridOptions = useMemo<GridOptions>(
    () => ({
      rowModelType: 'serverSide',
      serverSideDatasource: datasource,
      // Reveal the header filter and column-menu buttons only while the pointer
      // is over the column header; the v35 default keeps them always visible.
      suppressMenuHide: false,
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
        minWidth: DEFAULT_MIN_WIDTH,
      },
    }),
    [datasource, blockSize],
  )

  return (
    // Fills its parent so the toolbar block controls the height (fixed in the
    // normal layout, flex-1 in fullscreen — spec 0009).
    <div className="h-full min-h-0 w-full">
      <AgGridReact
        theme={dataTableTheme}
        columnDefs={colDefs}
        localeText={localeText}
        initialState={initialState}
        onGridReady={onGridReady}
        onColumnResized={handleColumnResized}
        onColumnMoved={handleColumnMoved}
        onColumnVisible={handleColumnVisible}
        onFilterChanged={onFilterChanged}
        onModelUpdated={handleModelUpdated}
        {...gridOptions}
      />
    </div>
  )
}
