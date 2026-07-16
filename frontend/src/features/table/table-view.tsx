import {
  forwardRef,
  useCallback,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react'
import { useTranslation } from 'react-i18next'
import type { GridApi, GridReadyEvent } from 'ag-grid-community'
import { Download, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DropdownMenuItem } from '@/components/ui/dropdown-menu'
import {
  ACTIONS_COLUMN_ID,
  DataTable,
} from '@/components/data-table/data-table'
import { useAbilities } from '@/features/auth/use-abilities'
import { createSsrmDatasource } from '@/features/table/ssrm-datasource'
import { SavedViewsSlot } from '@/features/table/saved-views-slot'
import { TableToolbar } from '@/features/table/table-toolbar'
import { useTableToolbarState } from '@/features/table/use-table-toolbar-state'
import { AdvancedFilterPanel, ADVANCED_FILTER_PANEL_ANIMATION } from '@/features/table/advanced-filters/advanced-filter-panel'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { useTableAdvancedFilters } from '@/features/table/advanced-filters/use-table-advanced-filters'
import { useBulkDelete } from '@/features/table/use-bulk-delete'
import { ExportDialog } from '@/features/exports/export-dialog'
import {
  createRowActionsRenderer,
  INLINE_ACTION_LIMIT,
  type RowActionHandler,
  type RowActionsOptions,
} from '@/features/table/row-actions'
import { useTableConfig } from '@/features/table/use-table-config'
import {
  toColumnPreferences,
  useResetTablePreferences,
  useSaveTablePreferences,
} from '@/features/table/use-table-preferences'
import {
  useResetTableFilters,
  useSaveTableFilters,
} from '@/features/table/use-table-filters'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Debounce window for persisting layout changes after the user stops editing. */
const PERSIST_DEBOUNCE_MS = 500

/** Stable empty filter model (module-level so its identity never changes). */
const EMPTY_FILTER_MODEL: Record<string, unknown> = {}

/** Imperative handle exposed by the generic table to its domain adapter. */
export interface TableViewHandle {
  /** Purges and reloads the SSRM cache (call after a CRUD mutation). */
  refresh: () => void
}

interface TableViewProps extends RowActionsOptions {
  /** Domain key selecting the server-side table definition (e.g. "users"). */
  domain: string
  /** Per-domain custom cell renderers, keyed by column id. Optional. */
  renderers?: TableRendererMap
  /**
   * Handler invoked when a row action fires. The generic table only renders the
   * affordance (crossing `row.actions` with the catalog); the concrete behavior
   * (open sheet, run delete, …) belongs to the domain adapter.
   */
  onAction: RowActionHandler
  /**
   * Import action, threaded through to the toolbar's `importSlot` (spec 0012).
   * The adapter owns the permission gate (`<Can>`) and the dialog; TableView
   * only forwards the node.
   */
  importSlot?: ReactNode
}

/**
 * Generic, domain-driven table. Given a `domain`, it loads the backend config,
 * builds the SSRM datasource, and mounts the agnostic DataTable wrapper fused
 * under a single unified toolbar (spec 0009: search + row count on the left;
 * filter toggle, saved views, options and fullscreen on the right — one bordered
 * block, no detached buttons). It owns loading/error/empty states, the SSRM
 * refresh mechanism, and the toolbar's client state (search term, floating
 * filters, fullscreen), but holds NO domain logic: custom rendering and action
 * behavior arrive entirely via props (`renderers`, `onAction`, `isBusy`,
 * `decorateRow`).
 *
 * Adding a new domain requires no change here — only a new adapter that mounts
 * this component with its `domain`, renderer map and action handler.
 */
export const TableView = forwardRef<TableViewHandle, TableViewProps>(
  function TableView(
    { domain, renderers, onAction, isBusy, decorateRow, iconMap, importSlot },
    ref,
  ) {
    const { t } = useTranslation()
    const { data: config, isPending, isError, refetch } = useTableConfig(domain)

    // Export is generic (spec 0014): TableView owns the grid api, so it gates,
    // builds and mounts the export affordance itself — no per-module wiring.
    const { can } = useAbilities()
    const canExport = can(`${domain}.export`)
    const [exportOpen, setExportOpen] = useState(false)

    const savePreferences = useSaveTablePreferences(domain)
    const resetPreferences = useResetTablePreferences(domain)
    const saveFilters = useSaveTableFilters(domain)
    const resetFilters = useResetTableFilters(domain)

    // Bumped on reset (layout or filters) to force a clean grid remount with the
    // refetched config, so AG Grid drops the user's in-memory column/filter state
    // deterministically.
    const [layoutVersion, setLayoutVersion] = useState(0)

    // Reflects whether the user has changed columns THIS session, for immediate
    // feedback; combined with the persisted `config.customized` (true after a
    // reload when a saved layout exists). The "Reset layout" action shows only
    // when the layout is customized.
    const [customizedLocally, setCustomizedLocally] = useState(false)
    const isCustomized = customizedLocally || (config?.customized ?? false)

    // Same immediate-feedback pattern for the saved filter state: a "Reset
    // filters" action shows whenever filters are active (this session or persisted).
    const [filtersCustomizedLocally, setFiltersCustomizedLocally] =
      useState(false)
    const isFilterCustomized =
      filtersCustomizedLocally || (config?.filtersCustomized ?? false)

    // The saved filterModel replayed into the grid on mount. Stable identity per
    // config load so it can seed the persisted-baseline ref below.
    const initialFilterModel = useMemo(
      () => config?.filterState ?? EMPTY_FILTER_MODEL,
      [config?.filterState],
    )

    // SSRM rows are not cached by TanStack Query, so they cannot be invalidated
    // through the queryClient. We hold the grid API and purge its server-side
    // cache directly. Stored in state (not a ref) so the imperative handle picks
    // up the API once the grid is ready.
    const [gridApi, setGridApi] = useState<GridApi | null>(null)
    const handleGridReady = useCallback((event: GridReadyEvent) => {
      setGridApi(event.api)
    }, [])

    // Purges and reloads the SSRM cache; shared by the imperative handle (used
    // by domain adapters after their own CRUD mutations) and the generic
    // bulk-delete flow below.
    const refreshGrid = useCallback(() => {
      gridApi?.refreshServerSide({ purge: true })
    }, [gridApi])

    // Bulk selection (current page only, per the SSRM select-all contract) and
    // the generic bulk-delete flow, gated on the domain's own action catalog:
    // the affordance only shows up when the backend actually exposes 'delete'
    // for this user (already permission-filtered server-side).
    const [selectedIds, setSelectedIds] = useState<number[]>([])
    const canBulkDelete = useMemo(
      () => config?.actions.some((action) => action.key === 'delete') ?? false,
      [config],
    )
    const { runBulkDelete, isDeleting } = useBulkDelete({
      domain,
      gridApi,
      refresh: refreshGrid,
    })
    const handleBulkDelete = useCallback(async () => {
      const didDelete = await runBulkDelete(selectedIds)
      if (didDelete) {
        setSelectedIds([])
      }
    }, [runBulkDelete, selectedIds])

    // The domain's global quick-search allow-list (spec 0009); empty ⇒ no search
    // box. Drives both the search affordance and the placeholder labels.
    const searchable = useMemo(
      () => config?.searchable ?? [],
      [config?.searchable],
    )
    const searchEnabled = searchable.length > 0

    // Client-only toolbar state (search term + ⌘K, floating filters, fullscreen,
    // live row count), owned by a dedicated hook so this component stays a thin
    // orchestrator (engineering.md §6).
    const toolbar = useTableToolbarState({ gridApi, searchEnabled })

    // The domain's advanced filter catalog (spec 0032); empty ⇒ the toolbar
    // hides the toggle entirely and the panel never mounts. Draft/applied
    // state, dependencies and persistence are owned by the dedicated hook;
    // Apply/Reset purge-reload the grid exactly once via `refreshGrid`.
    const { descriptors: advancedFilterDescriptors, filters: advancedFilters } =
      useTableAdvancedFilters({
        domain,
        descriptors: config?.advancedFilters,
        applied: config?.appliedAdvancedFilters,
        onApplied: refreshGrid,
      })

    // One datasource instance per domain; stable across re-renders. The current
    // search term and applied advanced filters are read lazily via getters, so
    // typing/toggling never rebuilds it (the grid is purge-reloaded instead).
    const datasource = useMemo(
      () => createSsrmDatasource(domain, toolbar.getSearchTerm, advancedFilters.getApplied),
      [domain, toolbar.getSearchTerm, advancedFilters.getApplied],
    )

    useImperativeHandle(ref, () => ({ refresh: refreshGrid }), [refreshGrid])

    // The domain's real column ids: mirrors the server's Rule::in allow-list, so
    // synthetic grid columns (row-actions, selection) are dropped from the saved
    // layout and a persist can never 422 on an unknown column id.
    const knownColumnIds = useMemo(
      () => new Set((config?.columns ?? []).map((column) => column.id)),
      [config?.columns],
    )

    // Persist the user's column layout, debounced so a drag/resize burst yields a
    // single save. The full current state is read from the grid and sent to the
    // backend, which computes the sparse delta (the frontend never diffs).
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
    const handleColumnStateChanged = useCallback(() => {
      if (!gridApi) {
        return
      }
      if (debounceRef.current) {
        clearTimeout(debounceRef.current)
      }
      // The user just changed columns → offer reset immediately, before the
      // debounced save round-trips.
      setCustomizedLocally(true)
      debounceRef.current = setTimeout(() => {
        const preferences = toColumnPreferences(
          gridApi.getColumnState(),
          knownColumnIds,
        )
        savePreferences.mutate(preferences)
      }, PERSIST_DEBOUNCE_MS)
    }, [gridApi, knownColumnIds, savePreferences])

    // Debounce filter persistence, and hold the last-persisted model (serialized)
    // so the grid's own echo of the saved filters on mount is not re-saved.
    const filterDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
    const lastPersistedFilterRef = useRef<string>(JSON.stringify(EMPTY_FILTER_MODEL))
    useEffect(() => {
      lastPersistedFilterRef.current = JSON.stringify(initialFilterModel)
    }, [initialFilterModel])

    const handleFilterChanged = useCallback(() => {
      if (!gridApi) {
        return
      }
      const model = gridApi.getFilterModel()
      const serialized = JSON.stringify(model)
      // Skip echoes and no-op refires: only a real change is persisted.
      if (serialized === lastPersistedFilterRef.current) {
        return
      }
      lastPersistedFilterRef.current = serialized
      setFiltersCustomizedLocally(Object.keys(model).length > 0)
      if (filterDebounceRef.current) {
        clearTimeout(filterDebounceRef.current)
      }
      filterDebounceRef.current = setTimeout(() => {
        saveFilters.mutate({ filterModel: model })
      }, PERSIST_DEBOUNCE_MS)
    }, [gridApi, saveFilters])

    // Flush any pending debounce on unmount so the last change is not lost.
    useEffect(
      () => () => {
        if (debounceRef.current) {
          clearTimeout(debounceRef.current)
        }
        if (filterDebounceRef.current) {
          clearTimeout(filterDebounceRef.current)
        }
      },
      [],
    )

    // Placeholder built from the searchable columns' localized labels, mirroring
    // the backend allow-list (e.g. "Cerca nome/email…").
    const searchPlaceholder = useMemo(() => {
      if (!config || searchable.length === 0) {
        return t('table.search')
      }
      const labels = searchable
        .map((id) => config.columns.find((column) => column.id === id))
        .filter((column): column is NonNullable<typeof column> => Boolean(column))
        .map((column) => t(column.label))
      return t('table.searchPlaceholder', { columns: labels.join('/') })
    }, [config, searchable, t])

    const handleResetLayout = useCallback(async () => {
      try {
        await resetPreferences.mutateAsync()
        // Refetch defaults BEFORE remounting so the new grid mounts on the pure
        // PHP default layout, then bump the key to rebuild it cleanly.
        await refetch()
        setCustomizedLocally(false)
        setLayoutVersion((version) => version + 1)
        toast.success(t('table.layoutReset'))
      } catch {
        toast.error(t('table.layoutError'))
      }
    }, [resetPreferences, refetch, t])

    const handleResetFilters = useCallback(async () => {
      try {
        // Drop any pending save so it can't re-persist the filters we are clearing.
        if (filterDebounceRef.current) {
          clearTimeout(filterDebounceRef.current)
        }
        await resetFilters.mutateAsync()
        // Refetch BEFORE remounting so the grid mounts with an empty filterModel,
        // then bump the key to rebuild it cleanly (SSRM re-queries unfiltered).
        await refetch()
        lastPersistedFilterRef.current = JSON.stringify(EMPTY_FILTER_MODEL)
        setFiltersCustomizedLocally(false)
        setLayoutVersion((version) => version + 1)
        toast.success(t('table.filtersReset'))
      } catch {
        toast.error(t('table.filtersError'))
      }
    }, [resetFilters, refetch, t])

    const renderRowActions = useMemo(() => {
      if (!config) {
        return undefined
      }
      return createRowActionsRenderer(config.actions, onAction, {
        isBusy,
        decorateRow,
        iconMap,
      })
    }, [config, onAction, isBusy, decorateRow, iconMap])

    let content: ReactNode
    if (isPending) {
      content = (
        <div className="flex h-full flex-col gap-2 p-3">
          {Array.from({ length: 8 }).map((_, index) => (
            <Skeleton key={index} className="h-8 w-full" />
          ))}
        </div>
      )
    } else if (isError) {
      content = (
        <div className="flex h-full flex-col items-start gap-3 p-4">
          <p className="text-sm text-destructive">{t('table.loadError')}</p>
          <Button variant="outline" size="sm" onClick={() => void refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      )
    } else if (config.columns.length === 0) {
      content = (
        <p className="p-4 text-sm text-muted-foreground">
          {t('table.emptyConfig')}
        </p>
      )
    } else {
      content = (
        <DataTable
          key={layoutVersion}
          domain={domain}
          columns={config.columns}
          datasource={datasource}
          blockSize={config.defaultPagination.limit}
          cellRenderers={renderers}
          renderRowActions={renderRowActions}
          actionsHeaderLabel="table.actionsHeader"
          actionsColumnHasOverflow={config.actions.length > INLINE_ACTION_LIMIT}
          onGridReady={handleGridReady}
          onColumnStateChanged={handleColumnStateChanged}
          initialFilterModel={initialFilterModel}
          onFilterChanged={handleFilterChanged}
          onRowCountChanged={toolbar.setRowCount}
          enableSelection={canBulkDelete}
          onSelectionChanged={setSelectedIds}
        />
      )
    }

    const savedViewsSlot = (
      <SavedViewsSlot
        domain={domain}
        gridApi={gridApi}
        config={config}
        advancedFilters={advancedFilters}
        onFilterModelApplied={setFiltersCustomizedLocally}
      />
    )

    const bulkActionsSlot =
      canBulkDelete && selectedIds.length > 0 ? (
        <div className="flex shrink-0 items-center gap-2">
          <span className="hidden whitespace-nowrap text-xs font-medium text-muted-foreground sm:inline">
            {t('table.selectedCount', { count: selectedIds.length })}
          </span>
          <Button
            type="button"
            variant="destructive"
            size="sm"
            disabled={isDeleting}
            onClick={() => void handleBulkDelete()}
          >
            <Trash2 aria-hidden="true" />
            {t('table.deleteSelected', { count: selectedIds.length })}
          </Button>
        </div>
      ) : null

    const exportSlot = canExport ? (
      <DropdownMenuItem
        onSelect={(event) => {
          event.preventDefault()
          setExportOpen(true)
        }}
      >
        <Download aria-hidden="true" />
        {t('exports.action')}
      </DropdownMenuItem>
    ) : null

    return (
      <>
        <div
          className={cn(
            'flex min-h-0 flex-col',
            toolbar.fullscreen &&
              'fixed inset-0 z-50 bg-background/80 p-3 backdrop-blur-sm sm:p-4',
          )}
        >
          <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <TableToolbar
              searchEnabled={searchEnabled}
              searchPlaceholder={searchPlaceholder}
              searchInputRef={toolbar.searchInputRef}
              searchValue={toolbar.searchInput}
              onSearchChange={toolbar.setSearchInput}
              searchShortcut={toolbar.searchShortcut}
              rowCount={toolbar.rowCount}
              bulkActionsSlot={bulkActionsSlot}
              filtersActive={isFilterCustomized}
              onResetFilters={() => void handleResetFilters()}
              resettingFilters={resetFilters.isPending}
              layoutCustomized={isCustomized}
              onResetLayout={() => void handleResetLayout()}
              resettingLayout={resetPreferences.isPending}
              fullscreen={toolbar.fullscreen}
              onToggleFullscreen={toolbar.toggleFullscreen}
              advancedFiltersEnabled={advancedFilterDescriptors.length > 0}
              advancedFiltersOpen={toolbar.advancedFiltersOpen}
              onToggleAdvancedFilters={toolbar.toggleAdvancedFilters}
              advancedFiltersActiveCount={advancedFilters.activeCount}
              savedViewsSlot={savedViewsSlot}
              importSlot={importSlot}
              exportSlot={exportSlot}
            />

            {advancedFilterDescriptors.length > 0 ? (
              <Collapsible open={toolbar.advancedFiltersOpen}>
                <CollapsibleContent className={ADVANCED_FILTER_PANEL_ANIMATION}>
                  <AdvancedFilterPanel descriptors={advancedFilterDescriptors} filters={advancedFilters} />
                </CollapsibleContent>
              </Collapsible>
            ) : null}

            <div
              className={cn(
                'min-h-0 w-full',
                toolbar.fullscreen ? 'flex-1' : 'h-[600px]',
              )}
            >
              {content}
            </div>
          </div>
        </div>

        {canExport && config ? (
          <ExportDialog
            domain={domain}
            open={exportOpen}
            onOpenChange={setExportOpen}
            gridApi={gridApi}
            columns={config.columns}
            actionsColumnId={ACTIONS_COLUMN_ID}
            search={toolbar.getSearchTerm()}
          />
        ) : null}
      </>
    )
  },
)
