/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { formatDecimal } from '@/features/products/column-renderers'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { GeoScopeBadge } from '@/features/geo/geo-scope-badge'
import { geoScopePlaceName, type GeoScope, type GeoScopeNames } from '@/features/geo/geo-scope'
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
 * `pipeline_status`, `source` — none of these carry a color token on the
 * `campaigns` table, unlike `projects.pipeline_status`, since
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

/**
 * Renders the derived, display-only `geo_scope` column (spec 0027 D-2) as the
 * shared `<GeoScopeBadge>` (reused verbatim from `features/geo`, never
 * duplicated) — the scope label plus the matching level's place name, picked
 * out of this row's own `country`/`state`/`province`/`city` cells (the row
 * already carries exactly the `GeoScopeNames` shape via `RelationCell`'s
 * `CampaignRelationRef`) via `geoScopePlaceName`. Em dash when there is no
 * geo at all.
 */
function GeoScopeCell({ value, data }: ICellRendererParams) {
  const scope = value as GeoScope | null | undefined
  const row = data as GeoScopeNames | undefined
  const place = scope && row ? geoScopePlaceName(scope, row) : null
  if (!scope || !place) {
    return <EmptyCell />
  }
  return <GeoScopeBadge scope={scope} place={place} />
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
 * the shared domain-agnostic renderer (spec 0023). `country`/`state`/
 * `province`/`city`/`geo_scope` (spec 0027 BR-5/D-2, AC-013) are the MERGED
 * campaign-or-project geo, display-only (`CampaignColumnCatalog`): the 4
 * relations reuse the existing `RelationCell`, `geo_scope` gets its own
 * `<GeoScopeBadge>`-based cell.
 */
export const campaignColumnRenderers: TableRendererMap = {
  project: (params) => <RelationCell {...params} />,
  registry: (params) => <RelationCell {...params} />,
  pipeline_status: (params) => <RelationCell {...params} />,
  source: (params) => <RelationCell {...params} />,
  country: (params) => <RelationCell {...params} />,
  state: (params) => <RelationCell {...params} />,
  province: (params) => <RelationCell {...params} />,
  city: (params) => <RelationCell {...params} />,
  geo_scope: (params) => <GeoScopeCell {...params} />,
  start_date: (params) => <DateCell {...params} />,
  end_date: (params) => <DateCell {...params} />,
  total_budget: (params) => <TotalBudgetCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
