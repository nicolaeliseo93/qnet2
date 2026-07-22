/**
 * AG Grid popup cell editor for a `relation` column (spec 0054 D-7): composes
 * the shared `AsyncPaginatedSelect` (ADR 0011) UNCHANGED — same search,
 * pagination and hydration behavior as every other relation picker in the app
 * (`components/form/relation-select-field.tsx`) — inside AG Grid's own popup
 * layer instead of a form field.
 *
 * The row already carries the `{id, name}` projection for this column
 * (backend `mapRow`'s related-row summary), so the trigger's label is known
 * up front — no extra hydration query. Picking a value (or clearing it) both
 * commits (`onValueChange`) and closes the cell editor (`stopEditing`)
 * immediately, mirroring `agRichSelectCellEditor`'s single-pick UX.
 *
 * `defaultOpen` (added by `ui-design` to `AsyncPaginatedSelect` for exactly
 * this case) mounts the searchable list already expanded: the single click
 * that starts the cell edit is the only click the operator needs — matching
 * the client's explicit single-click decision (D-9/0053), not the two-click
 * flow a closed trigger would have required.
 *
 * Popup rendering is declared statically on the colDef (`cellEditorPopup:
 * true`, `cell-editor-registry.ts`) — the functional-component API has no
 * `isPopup()` callback of its own (that is only the class/imperative editor
 * protocol).
 */
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TableRow } from '@/features/table/types'

/** A relation column's cell value: the related row's `{id, name}` projection, or `null`. */
export interface RelationCellValue {
  id: number
  name: string
}

/** Extra `cellEditorParams` for this editor (spec 0054 D-1): which `/for-select` resource feeds the dropdown. */
export interface RelationCellEditorParams {
  resource: string
}

export function RelationCellEditor(
  props: CustomCellEditorProps<TableRow, RelationCellValue | null> & RelationCellEditorParams,
) {
  const { t } = useTranslation()
  const { value, onValueChange, stopEditing, resource } = props

  // Both a pick and a clear commit immediately and close the editor —
  // `onItemChange` alone carries every case `AsyncPaginatedSelect` fires
  // (the full item on pick, `null` on clear), so a single handler suffices.
  const handleItemChange = (item: ForSelectItem | null) => {
    onValueChange(item ? { id: item.id, name: item.label } : null)
    stopEditing()
  }

  return (
    <div className="w-64 rounded-md border border-border bg-popover p-1.5 shadow-md">
      <AsyncPaginatedSelect
        resource={resource}
        value={value?.id ?? null}
        selectedItem={value ? { id: value.id, label: value.name } : null}
        defaultOpen
        onChange={() => {
          // Real commit happens in `onItemChange`, fired alongside with the
          // full item (or `null`) — see above.
        }}
        onItemChange={handleItemChange}
        labels={{
          placeholder: t('table.relationEditor.placeholder'),
          searchPlaceholder: t('table.relationEditor.searchPlaceholder'),
          empty: t('table.relationEditor.empty'),
          error: t('table.relationEditor.error'),
          clearLabel: t('table.relationEditor.clear'),
          triggerLabel: t('table.relationEditor.trigger'),
          retry: t('table.relationEditor.retry'),
        }}
      />
    </div>
  )
}
