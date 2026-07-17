import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Check, X, type LucideIcon } from 'lucide-react'
import i18n from '@/i18n'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
  BADGE_BASE,
  BADGE_COLOR_CLASSES,
  CELL_WRAPPER,
  EmptyCell,
  badgeColorClass,
} from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { formatDecimal } from '@/features/products/column-renderers'
import { GeoScopeBadge } from '@/features/geo/geo-scope-badge'
import { geoScopePlaceName, type GeoScope, type GeoScopeNames } from '@/features/geo/geo-scope'
import type { StatusGroupValue } from '@/features/status-reorder/types'

/**
 * Cross-module cell library. Every table (projects, campaigns, leads, imports,
 * status configurators) renders the SAME component for the same kind of data —
 * a relation, a colored status, a date, a money value, a boolean, a person —
 * so the grids read as one system. Purely presentational: the row value and its
 * semantics are unchanged; only how the value is drawn is enriched here.
 */

/** A hydrated relation carries either a `name` (most) or a composed `label` (operational sites). */
interface RelationLike {
  name?: string | null
  label?: string | null
}

function relationLabel(value: unknown): string | null {
  const relation = value as RelationLike | null | undefined
  const label = relation?.name ?? relation?.label
  return typeof label === 'string' && label !== '' ? label : null
}

/**
 * A `{id, name}` (or `{id, label}`) relation: the name, left-aligned, truncated
 * with a native tooltip when it overflows. An optional leading icon names the
 * relation KIND (a building for a company, a radio for a source, …) so columns
 * are legible at a glance. Left alignment preserves the previous plain-text cell.
 */
export function RelationCell({ value, icon: Icon }: ICellRendererParams & { icon?: LucideIcon }) {
  const label = relationLabel(value)
  if (!label) {
    return <EmptyCell align="left" />
  }
  return (
    <div className="flex h-full items-center gap-1.5 overflow-hidden">
      {Icon ? <Icon aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground/70" /> : null}
      <span className="truncate" title={label}>
        {label}
      </span>
    </div>
  )
}

/** A status relation `{name, color}`: colored pill with a leading solid status dot. */
interface StatusLike {
  name?: string | null
  color?: string | null
}

/**
 * Renders a status relation (`pipeline_status`, `lead_status`) as a colored
 * badge with a leading solid dot in the token's strong shade — the enterprise
 * status-chip look. Colorless statuses fall back to the neutral badge.
 */
export function StatusBadgeCell({ value }: ICellRendererParams) {
  const status = value as StatusLike | null | undefined
  const name = status?.name
  if (typeof name !== 'string' || name === '') {
    return <EmptyCell />
  }
  const dotClass = swatchClassFor(status?.color)
  return (
    <div className={CELL_WRAPPER}>
      <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', badgeColorClass(status?.color))}>
        {dotClass ? (
          <span className={cn('size-1.5 shrink-0 rounded-full', dotClass)} aria-hidden="true" />
        ) : null}
        <span className="truncate">{name}</span>
      </Badge>
    </div>
  )
}

/**
 * A short record `code` (e.g. "PRJ-001") as a compact monospace badge, so the
 * identifier column reads as a tag rather than plain text. Left-aligned to sit
 * at the start of the column; em dash when the code is missing.
 */
export function CodeBadgeCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell align="left" />
  }
  return (
    <div className="flex h-full items-center overflow-hidden">
      <Badge variant="secondary" className={cn(BADGE_BASE, 'font-mono', BADGE_COLOR_CLASSES.slate)}>
        <span className="truncate">{value}</span>
      </Badge>
    </div>
  )
}

/** A `Y-m-d` date (no time part), localized, digit-aligned. Left-aligned as before. */
export function DateCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell align="left" />
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return <EmptyCell align="left" />
  }
  return (
    <span className="tabular-nums text-foreground">
      {new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(date)}
    </span>
  )
}

/** A decimal money value, digit-aligned with a touch more weight for hierarchy. */
export function CurrencyCell({ value }: ICellRendererParams) {
  const formatted = formatDecimal(value)
  return formatted ? (
    <span className="font-medium tabular-nums text-foreground">{formatted}</span>
  ) : (
    <EmptyCell align="left" />
  )
}

/**
 * Colored yes/no badge with a leading icon (check when true, cross otherwise).
 * Icon plus text keeps it accessible — color is never the only signal.
 */
const BOOLEAN_BADGE_YES =
  'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200'
const BOOLEAN_BADGE_NO =
  'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200'

export function BooleanBadgeCell({ value }: ICellRendererParams) {
  if (typeof value !== 'boolean') {
    return <EmptyCell />
  }
  return (
    <div className={CELL_WRAPPER}>
      <Badge className={cn(BADGE_BASE, 'gap-1', value ? BOOLEAN_BADGE_YES : BOOLEAN_BADGE_NO)}>
        {value ? <Check aria-hidden="true" /> : <X aria-hidden="true" />}
        {i18n.t(value ? 'common.yes' : 'common.no')}
      </Badge>
    </div>
  )
}

/** Tone tokens used for person avatars — the lively half of the palette (no neutrals). */
const AVATAR_TONE_TOKENS = [
  'red', 'orange', 'amber', 'green', 'emerald', 'teal', 'blue', 'indigo', 'violet', 'purple', 'pink',
] as const

/** Two-letter initials from the first and last word of a name. */
function initialsOf(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean)
  const first = words[0] ?? ''
  const last = words.length > 1 ? (words[words.length - 1] ?? '') : ''
  return (first.charAt(0) + last.charAt(0)).toUpperCase() || '?'
}

/** Deterministic soft tone per name, so the same person keeps the same avatar color. */
function avatarToneClass(name: string): string {
  let hash = 0
  for (let index = 0; index < name.length; index += 1) {
    hash = (hash * 31 + name.charCodeAt(index)) >>> 0
  }
  const token = AVATAR_TONE_TOKENS[hash % AVATAR_TONE_TOKENS.length] ?? 'slate'
  return BADGE_COLOR_CLASSES[token] ?? BADGE_COLOR_CLASSES.slate
}

/**
 * A person relation (`operator`): a compact initials avatar plus the name. The
 * avatar tone is derived from the name, so the eye can track a person across
 * rows even before reading. Left-aligned; truncates with a native tooltip.
 */
export function UserAvatarCell({ value }: ICellRendererParams) {
  const name = relationLabel(value)
  if (!name) {
    return <EmptyCell align="left" />
  }
  const tone = avatarToneClass(name)
  return (
    <div className="flex h-full items-center gap-2 overflow-hidden">
      <Avatar className="size-5">
        <AvatarFallback className={cn('text-[0.625rem] font-semibold', tone)}>
          {initialsOf(name)}
        </AvatarFallback>
      </Avatar>
      <span className="truncate" title={name}>
        {name}
      </span>
    </div>
  )
}

/** A raw palette token rendered as a swatch dot + its localized name (color config columns). */
export function ColorSwatchCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  const token = typeof value === 'string' ? value : null
  if (!token) {
    return <EmptyCell align="left" />
  }
  const swatch = swatchClassFor(token)
  return (
    <div className="flex h-full items-center gap-2 overflow-hidden px-2">
      <span
        className={cn('size-3 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
        aria-hidden="true"
      />
      <span className="truncate">{t(`customFields.colors.${token}`)}</span>
    </div>
  )
}

/** Static swatch token per fixed status group (spec 0039: no per-row color stored). */
const GROUP_SWATCH_TOKENS: Record<StatusGroupValue, string> = {
  open: 'green',
  pending: 'orange',
  closed: 'red',
}

/**
 * The fixed 3-value status `group` as a colored dot + localized label. The label
 * i18n namespace differs per configurator (`pipelineStatuses` vs `leadStatuses`),
 * so the caller passes `labelPrefix`.
 */
export function GroupCell({
  value,
  labelPrefix,
}: ICellRendererParams & { labelPrefix: string }) {
  const { t } = useTranslation()
  const group = value as StatusGroupValue | null | undefined
  if (!group) {
    return <EmptyCell align="left" />
  }
  const swatch = swatchClassFor(GROUP_SWATCH_TOKENS[group])
  return (
    <div className="flex h-full items-center gap-2 overflow-hidden px-2">
      <span
        className={cn('size-3 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
        aria-hidden="true"
      />
      <span className="truncate">{t(`${labelPrefix}.${group}`)}</span>
    </div>
  )
}

/**
 * The derived, display-only `geo_scope` badge (spec 0027 D-2). With `withPlace`
 * the finest level's place name is picked from the row's own geo columns; without
 * it, the scope label shows alone (the row already carries the place columns).
 */
export function GeoScopeCell({
  value,
  data,
  withPlace = false,
}: ICellRendererParams & { withPlace?: boolean }) {
  const scope = value as GeoScope | null | undefined
  if (!scope) {
    return <EmptyCell />
  }
  if (!withPlace) {
    return (
      <div className={CELL_WRAPPER}>
        <GeoScopeBadge scope={scope} />
      </div>
    )
  }
  const row = data as GeoScopeNames | undefined
  const place = row ? geoScopePlaceName(scope, row) : null
  if (!place) {
    return <EmptyCell />
  }
  return (
    <div className={CELL_WRAPPER}>
      <GeoScopeBadge scope={scope} place={place} />
    </div>
  )
}
