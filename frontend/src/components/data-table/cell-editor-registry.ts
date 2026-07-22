/**
 * Per-`ColumnType` inline cell editor (spec 0053): the seam mapping a backend
 * column type to the AG Grid Enterprise editor it commits through. Same OCP
 * pattern as `CUSTOM_FIELD_COMPONENT_REGISTRY` and
 * `ADVANCED_FILTER_FIELD_REGISTRY` — adding a new `ColumnType` means one new
 * entry here, nothing else. Every editor listed ships with `AllEnterpriseModule`
 * (already registered, `ag-grid-setup.ts`): no new dependency.
 */
import { enumLabelOf } from '@/features/config/enum-label'
import type { ColumnType, TableColumn } from '@/features/table/types'

/** cellEditor name + optional per-column params, resolved once per colDef. */
export interface CellEditorSpec {
  cellEditor: string
  cellEditorParams?: (column: TableColumn) => Record<string, unknown>
}

/** Localizes an enum/badge/tags option value the same way `BadgeCell` does. */
function formatOptionLabel(column: TableColumn, value: unknown): string {
  const raw = String(value)
  if (column.enumKey) {
    return enumLabelOf(column.enumKey, raw)
  }
  return column.badges?.find((badge) => badge.value === raw)?.label ?? raw
}

/** `agRichSelectCellEditor` params shared by enum/badge/tags: the column's own option list. */
function richSelectParams(column: TableColumn, multiSelect: boolean): Record<string, unknown> {
  return {
    values: column.options ?? [],
    multiSelect,
    formatValue: (value: unknown) => formatOptionLabel(column, value),
  }
}

export const CELL_EDITOR_REGISTRY: Record<ColumnType, CellEditorSpec> = {
  text: { cellEditor: 'agTextCellEditor' },
  number: { cellEditor: 'agNumberCellEditor' },
  // AG Grid ships no datetime-local editor; the raw string is edited as plain
  // text and re-validated server-side (D-6) rather than inventing a custom
  // widget for this generic engine.
  datetime: { cellEditor: 'agTextCellEditor' },
  boolean: { cellEditor: 'agCheckboxCellEditor' },
  enum: { cellEditor: 'agRichSelectCellEditor', cellEditorParams: (column) => richSelectParams(column, false) },
  badge: { cellEditor: 'agRichSelectCellEditor', cellEditorParams: (column) => richSelectParams(column, false) },
  tags: { cellEditor: 'agRichSelectCellEditor', cellEditorParams: (column) => richSelectParams(column, true) },
}

/**
 * Defensive lookup (AC-023): `column.type` arrives as backend JSON, not a
 * compile-time-checked union, so a type the frontend does not (yet) know
 * about must resolve to "no editor" instead of indexing past the registry.
 */
export function resolveCellEditorSpec(type: ColumnType): CellEditorSpec | undefined {
  return Object.prototype.hasOwnProperty.call(CELL_EDITOR_REGISTRY, type)
    ? CELL_EDITOR_REGISTRY[type]
    : undefined
}
