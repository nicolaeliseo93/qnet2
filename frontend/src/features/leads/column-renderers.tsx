/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { LeadOperationalSiteRef, LeadRelationRef } from '@/features/leads/types'

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/** Renders a hydrated `{id, name}` relation column (referent, campaign, source, operator): the name, or an em dash. */
function RelationCell({ value }: ICellRendererParams) {
  const relation = value as LeadRelationRef | null | undefined
  if (!relation) {
    return <EmptyCell />
  }
  return <span>{relation.name}</span>
}

/**
 * Renders the operational-site column (BR-3): the server-composed
 * "{line1} - {city}" label, or an em dash. `operational_sites` has no `name`
 * column, so this is the only identity available.
 */
function OperationalSiteCell({ value }: ICellRendererParams) {
  const site = value as LeadOperationalSiteRef | null | undefined
  if (!site) {
    return <EmptyCell />
  }
  return <span>{site.label}</span>
}

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0024). `notes`
 * falls back to the AG Grid default cell; `created_at` reuses the shared
 * domain-agnostic renderer.
 */
export const leadColumnRenderers: TableRendererMap = {
  referent: (params) => <RelationCell {...params} />,
  campaign: (params) => <RelationCell {...params} />,
  operational_site: (params) => <OperationalSiteCell {...params} />,
  source: (params) => <RelationCell {...params} />,
  operator: (params) => <RelationCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
