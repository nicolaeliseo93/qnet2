/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import i18n from '@/i18n'
import { formatDecimal } from '@/features/products/column-renderers'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { ProjectRelationRef, ProjectStatusRef } from '@/features/projects/types'

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/**
 * Colored badge classes mirroring `BADGE_COLOR_CLASSES` in
 * `features/table/cell-renderers.tsx` — the same "backend color token → badge
 * classes" mapping duplicated per-domain in this codebase (see
 * `features/custom-fields/badge-color-tokens.ts`), since only `project_status`
 * here needs the full pill (every other domain color use is a dot swatch or a
 * grid-owned enum badge).
 */
const STATUS_BADGE_CLASSES: Record<string, string> = {
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

/** Renders a hydrated `{id, name}` relation column: the name, or an em dash. */
function RelationCell({ value }: ICellRendererParams) {
  const relation = value as ProjectRelationRef | null | undefined
  if (!relation) {
    return <EmptyCell />
  }
  return <span>{relation.name}</span>
}

/** Renders the `project_status` column as a colored badge (backend `#[Color]` token). */
function ProjectStatusCell({ value }: ICellRendererParams) {
  const status = value as ProjectStatusRef | null | undefined
  if (!status) {
    return <EmptyCell />
  }
  const colorClass = status.color ? STATUS_BADGE_CLASSES[status.color] : undefined
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <Badge variant="secondary" className={cn('h-5 min-h-5', colorClass)}>
        {status.name}
      </Badge>
    </div>
  )
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
export const projectColumnRenderers: TableRendererMap = {
  registry: (params) => <RelationCell {...params} />,
  project_status: (params) => <ProjectStatusCell {...params} />,
  source: (params) => <RelationCell {...params} />,
  business_function: (params) => <RelationCell {...params} />,
  state: (params) => <RelationCell {...params} />,
  product_category: (params) => <RelationCell {...params} />,
  partner: (params) => <RelationCell {...params} />,
  start_date: (params) => <DateCell {...params} />,
  end_date: (params) => <DateCell {...params} />,
  total_budget: (params) => <TotalBudgetCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
