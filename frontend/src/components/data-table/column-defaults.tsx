/**
 * Generic, domain-agnostic column defaults for the DataTable wrapper: cell
 * renderer and value-formatter fallbacks picked from the backend column
 * schema (`type`/`source`), never from a per-id registry. Split out of
 * `data-table.tsx` (300/500-line budget) so the selection logic is a plain,
 * directly-unit-testable module.
 *
 * `source:'custom'` columns (universal custom fields, spec 0021) carry a
 * dynamic id (`custom.<key>`) that no per-id renderer map can cover — these
 * fallbacks are what make them render/format correctly out of the box.
 */
import type { ICellRendererParams } from 'ag-grid-community'
import appI18n from '@/i18n'
import { formatBooleanFilterValue } from '@/components/data-table/column-filters'
import { BadgeCell } from '@/features/table/cell-renderers'
import type { TableColumn } from '@/features/table/types'

/** A custom cell renderer keyed by column id. Receives the AG Grid cell params. */
export type CellRenderer = (params: ICellRendererParams) => React.ReactNode

/**
 * Formats a custom-field `number` column's raw value (may arrive as a numeric
 * string) with the active UI locale's separators, blank when missing/invalid.
 * Only used for `source:'custom'` columns — native `number` columns keep
 * today's plain AG Grid display untouched.
 */
function formatCustomNumber(value: unknown): string {
  const numeric =
    typeof value === 'number'
      ? value
      : typeof value === 'string' && value.trim() !== ''
        ? Number(value)
        : NaN
  return Number.isFinite(numeric)
    ? new Intl.NumberFormat(appI18n.language).format(numeric)
    : ''
}

/**
 * Default cell value formatter for a column, when no cell renderer applies.
 * `tags` (native) joins the array for display. `source:'custom'` columns get
 * a formatter picked by `type` — boolean and number are the two custom types
 * that need one; enum (badge) and text/relation are handled by
 * `resolveCellRenderer`/plain text respectively. Native columns of those
 * types are untouched (the `source === 'custom'` guard).
 */
export function defaultValueFormatter(
  column: TableColumn,
  translate: (key: string) => string,
): ((value: unknown) => string) | undefined {
  if (column.type === 'tags') {
    return (value: unknown): string =>
      Array.isArray(value) ? value.join(', ') : String(value ?? '')
  }
  if (column.source === 'custom' && column.type === 'boolean') {
    return (value: unknown): string => formatBooleanFilterValue(value, translate)
  }
  if (column.source === 'custom' && column.type === 'number') {
    return formatCustomNumber
  }
  return undefined
}

/**
 * Whether a column renders as the generic enum badge fallback: native `badge`
 * columns and custom `enum` columns (`source:'custom'`) share the same
 * backend-supplied badge metadata shape (`badges`/`enumKey`), so both use the
 * same agnostic `BadgeCell` — no per-id renderer needed even though the
 * custom column id is dynamic.
 */
function isEnumBadgeColumn(column: TableColumn): boolean {
  return column.type === 'badge' || (column.source === 'custom' && column.type === 'enum')
}

/**
 * Picks the cell renderer for a column: an explicit per-id override from the
 * caller's map, else the generic enum-badge fallback, else `undefined` (AG
 * Grid's default text cell, optionally driven by `defaultValueFormatter`).
 */
export function resolveCellRenderer(
  column: TableColumn,
  cellRenderers?: Record<string, CellRenderer>,
): CellRenderer | undefined {
  const custom = cellRenderers?.[column.id]
  if (custom) {
    return custom
  }
  return isEnumBadgeColumn(column)
    ? (params: ICellRendererParams) => (
        <BadgeCell {...params} badges={column.badges} enumKey={column.enumKey} />
      )
    : undefined
}
