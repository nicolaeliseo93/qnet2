import type { GridApi } from 'ag-grid-community'
import { FilterViewsControl } from '@/features/table/filter-views-control'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type { TableConfig, TableRow } from '@/features/table/types'

interface SavedViewsSlotProps {
  domain: string
  gridApi: GridApi<TableRow> | null
  config: TableConfig | undefined
  advancedFilters: UseAdvancedFiltersResult
  onFilterModelApplied: (hasFilters: boolean) => void
}

/**
 * Wraps `FilterViewsControl` with the grid + advanced-filters wiring it needs
 * from `TableView` (applying a saved view restores BOTH the column filterModel
 * and the advanced filters — spec 0007/0032 AC-009). Extracted to its own
 * component purely to keep `table-view.tsx` under the size budget
 * (engineering.md §6); holds no state of its own.
 */
export function SavedViewsSlot({
  domain,
  gridApi,
  config,
  advancedFilters,
  onFilterModelApplied,
}: SavedViewsSlotProps) {
  if (!gridApi || !config) {
    return null
  }

  return (
    <FilterViewsControl
      domain={domain}
      currentFilters={gridApi.getFilterModel() ?? {}}
      currentAdvancedFilters={advancedFilters.activeValues}
      onApply={(filters, advancedFilterValues) => {
        gridApi.setFilterModel(filters)
        onFilterModelApplied(Object.keys(filters).length > 0)
        advancedFilters.applyValues(advancedFilterValues)
      }}
    />
  )
}
