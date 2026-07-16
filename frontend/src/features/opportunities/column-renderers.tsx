/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { formatDecimal } from '@/features/products/column-renderers'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { OpportunityRelationRef } from '@/features/opportunities/types'

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/** Renders a hydrated `{id, name}` relation column (registry, referent, commercial, supervisor, source, product_category): the name, or an em dash. */
function RelationCell({ value }: ICellRendererParams) {
  const relation = value as OpportunityRelationRef | null | undefined
  if (!relation) {
    return <EmptyCell />
  }
  return <span>{relation.name}</span>
}

/** Renders a `Y-m-d` date column, no time part. */
function DateCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell />
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return <EmptyCell />
  }
  return <span>{new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(date)}</span>
}

/** Renders the `estimated_value` decimal(15,2) column, em dash when null (mirrors `campaigns.total_budget`). */
function EstimatedValueCell({ value }: ICellRendererParams) {
  const formatted = formatDecimal(value)
  return formatted ? <span>{formatted}</span> : <EmptyCell />
}

/** Renders the `success_probability` 0..100 integer column as a percentage, em dash when null. */
function SuccessProbabilityCell({ value }: ICellRendererParams) {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return <EmptyCell />
  }
  return <span>{`${value}%`}</span>
}

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0040). `name`
 * falls back to the AG Grid default cell; `created_at` reuses the shared
 * domain-agnostic renderer.
 */
export const opportunityColumnRenderers: TableRendererMap = {
  registry: (params) => <RelationCell {...params} />,
  referent: (params) => <RelationCell {...params} />,
  commercial: (params) => <RelationCell {...params} />,
  supervisor: (params) => <RelationCell {...params} />,
  source: (params) => <RelationCell {...params} />,
  product_category: (params) => <RelationCell {...params} />,
  estimated_value: (params) => <EstimatedValueCell {...params} />,
  success_probability: (params) => <SuccessProbabilityCell {...params} />,
  start_date: (params) => <DateCell {...params} />,
  expected_close_date: (params) => <DateCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
