import { useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AgGridReact } from 'ag-grid-react'
import { themeQuartz, type GetRowIdParams, type GridOptions } from 'ag-grid-community'
import { AG_GRID_LOCALE_EN, AG_GRID_LOCALE_IT } from '@ag-grid-community/locale'
import { setupAgGrid } from '@/components/data-table/ag-grid-setup'
import { buildReviewColumnDefs } from '@/features/imports/wizard/review-columns'
import type { ReviewGeoGridContext } from '@/features/imports/wizard/review-geo-editor'
import { useReviewRows } from '@/features/imports/wizard/use-review-rows'
import type { ImportRunDetail, ImportRunRowCounts, ImportRunRowItem } from '@/features/imports/wizard/types'

// Register enterprise modules + license once, at module load (idempotent,
// mirrors `components/data-table/data-table.tsx`).
setupAgGrid()

/** Rows fetched per SSRM block; the review dataset is typically small (a single import file). */
const REVIEW_BLOCK_SIZE = 25

/**
 * Compact theme aligned to the app's design tokens (§ui-design), scoped to
 * this grid: `components/data-table/data-table.tsx` owns its own private
 * theme and is not touched here (read-only grids are out of this lane's
 * scope) — a small, self-contained token set is intentional, not duplication
 * of shared logic.
 */
const reviewGridTheme = themeQuartz.withParams({
  fontFamily: 'inherit',
  fontSize: 12,
  rowHeight: 30,
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
  wrapperBorderRadius: 6,
})

/** No-op counters callback for the read-only mode, which never edits a row. */
function noopRowUpdated(): void {}

export interface ReviewGridProps {
  domain: string
  run: ImportRunDetail
  /** Bubbled up from an inline edit so the caller (review step) can refresh its counters. */
  onRowUpdated?: (row: ImportRunRowItem, counts: ImportRunRowCounts) => void
  /**
   * Renders the same staged rows with every value column non-editable and no
   * `onCellValueChanged` wiring (spec 0034 AC-013): the concluded-run detail
   * page reuses this grid purely as a read-only viewer, on top of the
   * backend's own `PATCH .../rows/{row}` 422 outside `reviewing`.
   */
  readOnly?: boolean
}

/**
 * AG Grid SSRM datasource over the staged rows of a run (AC-023), with
 * inline editing on the mapped/extra value columns while `reviewing`:
 * `stopEditing` (AG Grid's own commit-on-blur/Enter) fires
 * `onCellValueChanged`, which PATCHes just the edited field and swaps the row
 * for the server's re-validated copy. `readOnly` renders the same rows
 * without any edit affordance (spec 0034).
 */
export function ReviewGrid({ domain, run, onRowUpdated = noopRowUpdated, readOnly = false }: ReviewGridProps) {
  const { t } = useTranslation('importWizard')
  const { i18n } = useTranslation()

  const localeText = useMemo(
    () => (i18n.language.startsWith('it') ? AG_GRID_LOCALE_IT : AG_GRID_LOCALE_EN),
    [i18n.language],
  )

  const { datasource, handleCellValueChanged, handleResolutionChange, handleApplyGeo } = useReviewRows({
    domain,
    importRunId: run.id,
    onRowUpdated,
  })

  const columnDefs = useMemo(
    () => buildReviewColumnDefs(run, t, readOnly, handleResolutionChange),
    [run, t, readOnly, handleResolutionChange],
  )

  const getRowId = useCallback((params: GetRowIdParams<ImportRunRowItem>) => String(params.data.id), [])

  // The geo popup's apply callback (spec 0038) is shared by all 4 geo
  // columns and never column-specific, so it travels via `gridOptions.context`
  // instead of `cellRendererParams` — every `ReviewGeoCell` reads it off
  // `params.context`, with no per-colDef prop drilling.
  const gridContext = useMemo<ReviewGeoGridContext>(() => ({ onApplyGeo: handleApplyGeo }), [handleApplyGeo])

  const gridOptions = useMemo<GridOptions<ImportRunRowItem>>(
    () => ({
      rowModelType: 'serverSide',
      serverSideDatasource: datasource,
      cacheBlockSize: REVIEW_BLOCK_SIZE,
      serverSideInitialRowCount: REVIEW_BLOCK_SIZE,
      pagination: true,
      paginationPageSize: REVIEW_BLOCK_SIZE,
      paginationPageSizeSelector: [REVIEW_BLOCK_SIZE, REVIEW_BLOCK_SIZE * 2],
      defaultColDef: { resizable: true, minWidth: 120 },
      getRowId,
      context: gridContext,
      singleClickEdit: !readOnly,
      stopEditingWhenCellsLoseFocus: !readOnly,
      onCellValueChanged: readOnly ? undefined : handleCellValueChanged,
    }),
    [datasource, getRowId, gridContext, handleCellValueChanged, readOnly],
  )

  return (
    <div
      className="flex h-[55vh] min-h-[320px] max-h-[640px] w-full flex-col"
      role="region"
      aria-label={t('review.gridLabel')}
    >
      <AgGridReact theme={reviewGridTheme} columnDefs={columnDefs} localeText={localeText} {...gridOptions} />
    </div>
  )
}
