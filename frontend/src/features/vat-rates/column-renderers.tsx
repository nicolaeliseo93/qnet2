/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Formats a VAT rate percentage using the active UI locale, em dash when
 * null/invalid. Accepts a numeric string too: Laravel's `decimal:2` cast
 * serializes `rate` as a string (e.g. "22.00").
 */
export function formatRate(value: unknown): string {
  const numeric =
    typeof value === 'number'
      ? value
      : typeof value === 'string' && value.trim() !== ''
        ? Number(value)
        : NaN
  if (!Number.isFinite(numeric)) {
    return ''
  }
  return new Intl.NumberFormat(i18n.language, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numeric)
}

/** Renders the `rate` decimal cell, em dash when null. */
function RateCell({ value }: ICellRendererParams) {
  const formatted = formatRate(value)
  return formatted ? <span>{formatted}%</span> : <span className="text-muted-foreground">—</span>
}

/**
 * Custom cell renderers keyed by the backend column `id`. `name` falls back
 * to the AG Grid default text cell; `created_at` reuses the shared
 * domain-agnostic renderer so the datetime formatting is not re-implemented
 * per domain (mirrors `sourceColumnRenderers`).
 */
export const vatRateColumnRenderers: TableRendererMap = {
  rate: (params) => <RateCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
