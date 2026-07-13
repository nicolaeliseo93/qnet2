/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { formatDecimal } from '@/features/products/column-renderers'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { CampaignRelationRef } from '@/features/campaigns/types'

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/**
 * Renders a hydrated `{id, name}` relation column (`project`, `registry`,
 * `project_status`, `source` — none of these carry a color token on the
 * `campaigns` table, unlike `projects.project_status`, since
 * `CampaignsTableDefinition::mapRow` resolves them all through the same plain
 * name-only `summarize()`): the name, or an em dash.
 */
function RelationCell({ value }: ICellRendererParams) {
  const relation = value as CampaignRelationRef | null | undefined
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

/** Renders the `total_budget` decimal column, em dash when null. */
function TotalBudgetCell({ value }: ICellRendererParams) {
  const formatted = formatDecimal(value)
  return formatted ? <span>{formatted}</span> : <EmptyCell />
}

/**
 * Custom cell renderers keyed by the backend column `id`. `code`/`name`/
 * `target_lead` fall back to the AG Grid default cell; `created_at` reuses
 * the shared domain-agnostic renderer (spec 0023).
 */
export const campaignColumnRenderers: TableRendererMap = {
  project: (params) => <RelationCell {...params} />,
  registry: (params) => <RelationCell {...params} />,
  project_status: (params) => <RelationCell {...params} />,
  source: (params) => <RelationCell {...params} />,
  start_date: (params) => <DateCell {...params} />,
  end_date: (params) => <DateCell {...params} />,
  total_budget: (params) => <TotalBudgetCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
