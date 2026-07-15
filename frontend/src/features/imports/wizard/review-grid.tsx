import { useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AgGridReact } from 'ag-grid-react'
import { themeQuartz, type GetRowIdParams, type GridOptions } from 'ag-grid-community'
import { AG_GRID_LOCALE_EN, AG_GRID_LOCALE_IT } from '@ag-grid-community/locale'
import { setupAgGrid } from '@/components/data-table/ag-grid-setup'
import { buildReviewColumnDefs } from '@/features/imports/wizard/review-columns'
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

export interface ReviewGridProps {
  domain: string
  run: ImportRunDetail
  /** Bubbled up from an inline edit so the caller (review step) can refresh its counters. */
  onRowUpdated: (row: ImportRunRowItem, counts: ImportRunRowCounts) => void
}

/**
 * AG Grid SSRM datasource over the staged rows of a reviewing run (AC-023),
 * with inline editing on the mapped/extra value columns: `stopEditing` (AG
 * Grid's own commit-on-blur/Enter) fires `onCellValueChanged`, which PATCHes
 * just the edited field and swaps the row for the server's re-validated copy.
 */
export function ReviewGrid({ domain, run, onRowUpdated }: ReviewGridProps) {
  const { t } = useTranslation('importWizard')
  const { i18n } = useTranslation()

  const localeText = useMemo(
    () => (i18n.language.startsWith('it') ? AG_GRID_LOCALE_IT : AG_GRID_LOCALE_EN),
    [i18n.language],
  )

  const columnDefs = useMemo(() => buildReviewColumnDefs(run, t), [run, t])

  const { datasource, handleCellValueChanged } = useReviewRows({
    domain,
    importRunId: run.id,
    onRowUpdated,
  })

  const getRowId = useCallback((params: GetRowIdParams<ImportRunRowItem>) => String(params.data.id), [])

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
      singleClickEdit: true,
      stopEditingWhenCellsLoseFocus: true,
      onCellValueChanged: handleCellValueChanged,
    }),
    [datasource, getRowId, handleCellValueChanged],
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
