/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { Check, X } from 'lucide-react'
import i18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { LeadOperationalSiteRef, LeadRelationRef, LeadStatusRef } from '@/features/leads/types'

/**
 * Colored badge classes mirroring `BADGE_COLOR_CLASSES` in
 * `features/table/cell-renderers.tsx` (not exported) and `STATUS_BADGE_CLASSES`
 * in `features/projects/column-renderers.tsx` (also not exported) — this is
 * the same "backend color token -> badge classes" map duplicated a third
 * time. Neither existing map is exported, and both files are outside this
 * teammate's write surface (spec 0029), so importing was not an option;
 * exporting one of them and sharing it is a follow-up worth doing centrally.
 * Exported here so `lead-detail.tsx` (same feature) reuses it instead of a
 * fourth copy.
 */
export const LEAD_STATUS_BADGE_CLASSES: Record<string, string> = {
  slate: 'border-transparent bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
  gray: 'border-transparent bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
  red: 'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200',
  orange: 'border-transparent bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-200',
  amber: 'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
  yellow: 'border-transparent bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200',
  green: 'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200',
  emerald: 'border-transparent bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
  teal: 'border-transparent bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-200',
  blue: 'border-transparent bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
  indigo: 'border-transparent bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200',
  violet: 'border-transparent bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200',
  purple: 'border-transparent bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-200',
  pink: 'border-transparent bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-200',
}

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/** Renders a hydrated `{id, name}` relation column (registry, campaign, source, operator): the name, or an em dash. */
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
 * Renders the `lead_status` column as a colored badge (backend color token,
 * spec 0029 AC-015). Mapped EXPLICITLY as `{id, name, color}` server-side
 * (not via `summarize()`), so unlike `pipeline_status` this cell is never
 * colorless (context/known_defect_not_ours in the spec).
 */
function LeadStatusCell({ value }: ICellRendererParams) {
  const status = value as LeadStatusRef | null | undefined
  if (!status) {
    return <EmptyCell />
  }
  const colorClass = status.color ? LEAD_STATUS_BADGE_CLASSES[status.color] : undefined
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <Badge variant="secondary" className={cn('h-5 min-h-5', colorClass)}>
        {status.name}
      </Badge>
    </div>
  )
}

/**
 * Colored tone classes for the `is_assigned` boolean badge (green = yes,
 * red = no), matching the users/business-functions boolean badges so boolean
 * status columns look the same across the app and adapt to dark mode.
 */
const BOOLEAN_BADGE_YES =
  'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200'
const BOOLEAN_BADGE_NO =
  'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200'

/**
 * Renders a derived boolean column (`is_assigned`, `is_converted`) as a
 * colored yes/no badge with a leading icon (check when true, cross otherwise).
 * Icon plus text keeps it accessible — color is not the only signal.
 */
function BooleanBadgeCell({ value }: ICellRendererParams) {
  if (typeof value !== 'boolean') {
    return <EmptyCell />
  }
  return (
    <div className="flex h-full items-center justify-center">
      <Badge className={cn('h-5 min-h-5', value ? BOOLEAN_BADGE_YES : BOOLEAN_BADGE_NO)}>
        {value ? <Check aria-hidden="true" /> : <X aria-hidden="true" />}
        {i18n.t(value ? 'common.yes' : 'common.no')}
      </Badge>
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0024). `notes`
 * falls back to the AG Grid default cell; `created_at` reuses the shared
 * domain-agnostic renderer.
 */
export const leadColumnRenderers: TableRendererMap = {
  registry: (params) => <RelationCell {...params} />,
  campaign: (params) => <RelationCell {...params} />,
  lead_status: (params) => <LeadStatusCell {...params} />,
  operational_site: (params) => <OperationalSiteCell {...params} />,
  source: (params) => <RelationCell {...params} />,
  operator: (params) => <RelationCell {...params} />,
  is_assigned: (params) => <BooleanBadgeCell {...params} />,
  is_converted: (params) => <BooleanBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
