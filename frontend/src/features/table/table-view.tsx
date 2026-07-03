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
import { FilterX, RotateCcw } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  ACTIONS_COLUMN_ID,
  DataTable,
} from '@/components/data-table/data-table'
import { createSsrmDatasource } from '@/features/table/ssrm-datasource'
import { FilterViewsControl } from '@/features/table/filter-views-control'
import {
  createRowActionsRenderer,
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
}

/**
 * Generic, domain-driven table. Given a `domain`, it loads the backend config,
 * builds the SSRM datasource, and mounts the agnostic DataTable wrapper. It owns
 * loading/error/empty states and the SSRM refresh mechanism, but holds NO
 * domain logic: custom rendering and action behavior arrive entirely via props
 * (`renderers`, `onAction`, `isBusy`, `decorateRow`).
 *
 * Adding a new domain requires no change here — only a new adapter that mounts
 * this component with its `domain`, renderer map and action handler.
 */
export const TableView = forwardRef<TableViewHandle, TableViewProps>(
  function TableView(
    { domain, renderers, onAction, isBusy, decorateRow, iconMap },
    ref,
  ) {
    const { t } = useTranslation()
    const { data: config, isPending, isError, refetch } = useTableConfig(domain)

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

    // One datasource instance per domain; stable across re-renders.
    const datasource = useMemo(() => createSsrmDatasource(domain), [domain])

    // SSRM rows are not cached by TanStack Query, so they cannot be invalidated
    // through the queryClient. We hold the grid API and purge its server-side
    // cache directly. Stored in state (not a ref) so the imperative handle picks
    // up the API once the grid is ready.
    const [gridApi, setGridApi] = useState<GridApi | null>(null)
    const handleGridReady = useCallback((event: GridReadyEvent) => {
      setGridApi(event.api)
    }, [])

    useImperativeHandle(
      ref,
      () => ({
        refresh: () => gridApi?.refreshServerSide({ purge: true }),
      }),
      [gridApi],
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
          ACTIONS_COLUMN_ID,
        )
        savePreferences.mutate(preferences)
      }, PERSIST_DEBOUNCE_MS)
    }, [gridApi, savePreferences])

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
        saveFilters.mutate(model)
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
        <div className="flex flex-col gap-2">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-[560px] w-full" />
        </div>
      )
    } else if (isError) {
      content = (
        <div className="flex flex-col items-start gap-3">
          <p className="text-sm text-destructive">{t('table.loadError')}</p>
          <Button variant="outline" size="sm" onClick={() => void refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      )
    } else if (config.columns.length === 0) {
      content = (
        <p className="text-sm text-muted-foreground">{t('table.emptyConfig')}</p>
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
          onGridReady={handleGridReady}
          onColumnStateChanged={handleColumnStateChanged}
          initialFilterModel={initialFilterModel}
          onFilterChanged={handleFilterChanged}
        />
      )
    }

    return (
      <div className="flex flex-col gap-4">
        <div className="flex items-center justify-end gap-2">
          {gridApi && config ? (
            <FilterViewsControl
              domain={domain}
              currentFilters={gridApi.getFilterModel() ?? EMPTY_FILTER_MODEL}
              onApply={(filters) => {
                gridApi.setFilterModel(filters)
                setFiltersCustomizedLocally(Object.keys(filters).length > 0)
              }}
            />
          ) : null}
          {isFilterCustomized && (
            <Button
              variant="secondary"
              size="xs"
              onClick={() => void handleResetFilters()}
              disabled={resetFilters.isPending}
            >
              <FilterX aria-hidden="true" />
              {t('table.resetFilters')}
            </Button>
          )}
          {isCustomized && (
            <Button
              variant="secondary"
              size="xs"
              onClick={() => void handleResetLayout()}
              disabled={resetPreferences.isPending}
            >
              <RotateCcw aria-hidden="true" />
              {t('table.resetLayout')}
            </Button>
          )}
        </div>

        {content}
      </div>
    )
  },
)
