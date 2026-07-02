/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import { createElement, useState, type ReactNode } from 'react'
import type { ICellRendererParams } from 'ag-grid-community'
import { Check, Copy } from 'lucide-react'
import i18n from '@/i18n'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { resolveEnumIcon } from '@/features/table/enum-icon-map'
import { enumLabelOf } from '@/features/config/enum-label'
import type { EnumBadge, PrimaryContact } from '@/features/table/types'

/** How long the copy button shows its "copied" confirmation, in ms. */
const COPY_FEEDBACK_MS = 1500

/**
 * A small icon button that copies `value` to the clipboard and briefly confirms
 * with a check. Lives inside the (hoverable) contact tooltip.
 */
function CopyButton({ value }: { value: string }) {
  const [copied, setCopied] = useState(false)

  const handleCopy = () => {
    void navigator.clipboard
      ?.writeText(value)
      .then(() => {
        setCopied(true)
        window.setTimeout(() => setCopied(false), COPY_FEEDBACK_MS)
      })
      .catch(() => {})
  }

  return (
    <Button
      type="button"
      variant="ghost"
      size="icon-xs"
      className="shrink-0"
      onClick={handleCopy}
      aria-label={i18n.t(copied ? 'table.copied' : 'table.copy')}
    >
      {copied ? (
        <Check aria-hidden="true" className="text-emerald-600 dark:text-emerald-400" />
      ) : (
        <Copy aria-hidden="true" />
      )}
    </Button>
  )
}

/**
 * Render a backend icon token as a decorative inline icon, or nothing when the
 * token is absent. Uses `createElement` (not JSX) so the resolved lucide
 * component is treated as data, not a component declared during render.
 */
function enumIcon(name: string | null | undefined, className?: string): ReactNode {
  const icon = resolveEnumIcon(name)
  return icon ? createElement(icon, { 'aria-hidden': 'true', className }) : null
}

/** Shared cell wrapper: vertically centered with breathing room from the edges. */
const CELL_WRAPPER = 'flex h-full w-full items-center justify-center px-2 py-1 overflow-hidden'

/** Consistent pill height so icon+label and label-only badges align across rows. */
const BADGE_BASE = 'h-5 min-h-5'

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/**
 * Domain-agnostic cell renderers shared by every table feature, so domains
 * (users, roles, …) never re-implement the same tags/date formatting. Keep only
 * truly generic cells here; domain-specific ones (e.g. a user email mailto link)
 * stay in the domain's own renderer map.
 */

/** How many tags are shown inline before collapsing into a “+N” badge. */
const MAX_VISIBLE_TAGS = 2

/** Formats an ISO datetime using the active UI locale, blank when missing. */
export function formatDateTime(value: unknown): string {
  if (typeof value !== 'string' || value === '') {
    return ''
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  return new Intl.DateTimeFormat(i18n.language, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

/** Optional per-tag label formatter (e.g. to localize permission names). */
type TagsCellProps = ICellRendererParams & {
  formatTag?: (tag: string) => string
}

/**
 * Renders a `tags` column (a string array such as roles or permissions) as a row
 * of badges, collapsing the overflow into a “+N” badge with a tooltip listing
 * every value. Centered to match the column header alignment. Pass `formatTag`
 * to localize the displayed labels (raw values stay the React keys).
 */
export function TagsCell({ value, formatTag }: TagsCellProps) {
  if (!Array.isArray(value) || value.length === 0) {
    return <EmptyCell />
  }

  const tags = value as string[]
  const label = (tag: string) => (formatTag ? formatTag(tag) : tag)
  const visibleTags = tags.slice(0, MAX_VISIBLE_TAGS)
  const hiddenCount = tags.length - visibleTags.length
  const allLabels = tags.map(label).join(', ')

  return (
    <div className={CELL_WRAPPER}>
      <div className="flex max-w-full flex-wrap items-center justify-center gap-1.5">
        {visibleTags.map((tag) => (
          <Badge key={tag} variant="secondary" className={BADGE_BASE}>
            {label(tag)}
          </Badge>
        ))}
        {hiddenCount > 0 && (
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger asChild>
                <Badge
                  variant="outline"
                  className={cn(BADGE_BASE, 'cursor-default')}
                  tabIndex={0}
                  aria-label={allLabels}
                >
                  +{hiddenCount}
                </Badge>
              </TooltipTrigger>
              <TooltipContent side="top">{allLabels}</TooltipContent>
            </Tooltip>
          </TooltipProvider>
        )}
      </div>
    </div>
  )
}

/**
 * Maps a backend color token (the enum's #[Color], e.g. "blue", "violet") to a
 * badge style. This is the SINGLE place that translates color tokens to classes,
 * so the table stays domain-agnostic: any enum-driven badge column reuses it.
 * Unknown/missing tokens fall back to the neutral `secondary` badge.
 */
const BADGE_COLOR_CLASSES: Record<string, string> = {
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

/** A `badge` column cell, carrying the column's backend-supplied badge metadata. */
type BadgeCellProps = ICellRendererParams & {
  badges?: EnumBadge[]
  /** Domain-enum key for i18n label localization; see `TableColumn.enumKey`. */
  enumKey?: string
}

/**
 * Renders a `badge` column: maps the row's enum value to its backend metadata
 * (color/icon) and renders a colored badge. The label is localized from the
 * frontend i18n resources when the column declares an `enumKey`, otherwise it
 * falls back to the backend-supplied label. Em dash when the value is empty or
 * has no matching metadata (e.g. a user with no personal-data card).
 */
export function BadgeCell({ value, badges, enumKey }: BadgeCellProps) {
  const meta = Array.isArray(badges)
    ? badges.find((badge) => badge.value === value)
    : undefined

  if (meta === undefined) {
    return <EmptyCell />
  }

  const colorClass = meta.color ? BADGE_COLOR_CLASSES[meta.color] : undefined
  const label = enumKey ? enumLabelOf(enumKey, meta.value) : meta.label

  return (
    <div className={CELL_WRAPPER}>
      <Badge variant="secondary" className={cn(BADGE_BASE, colorClass)}>
        {enumIcon(meta.icon)}
        {label}
      </Badge>
    </div>
  )
}

/**
 * Renders a numeric `number` column as a centered neutral badge with the value
 * inside (e.g. the count of users per role). Domain-agnostic: any count/number
 * column can opt in via its renderer map. Em dash when the value is not a finite
 * number. `tabular-nums` keeps digits aligned across rows.
 */
export function CountCell({ value }: ICellRendererParams) {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return <EmptyCell />
  }

  return (
    <div className={CELL_WRAPPER}>
      <Badge variant="secondary" className={cn(BADGE_BASE, 'tabular-nums')}>
        {value}
      </Badge>
    </div>
  )
}

/** Renders a formatted datetime cell, em dash when empty/invalid. */
export function DateTimeCell({ value }: ICellRendererParams) {
  const formatted = formatDateTime(value)
  return formatted ? (
    <span>{formatted}</span>
  ) : (
    <span className="text-muted-foreground">—</span>
  )
}

/**
 * Subtle per-contact-type icon tint (text color only; the pill stays neutral).
 * Keeps the row calm while still letting the eye tell contact types apart. The
 * icon is decorative (the label names the type), so this never carries meaning.
 */
const CONTACT_ICON_TINT: Record<string, string> = {
  mail: 'text-blue-600 dark:text-blue-300',
  phone: 'text-emerald-600 dark:text-emerald-300',
  smartphone: 'text-emerald-600 dark:text-emerald-300',
  printer: 'text-slate-500 dark:text-slate-300',
  'shield-check': 'text-violet-600 dark:text-violet-300',
  globe: 'text-amber-600 dark:text-amber-300',
}

/** A `primary_contact` cell value: the row's array of primary contacts. */
type ContactsCellProps = ICellRendererParams & {
  value?: PrimaryContact[]
}

/**
 * Renders the `primary_contact` column as a single compact badge showing the
 * number of primary contacts. The tooltip holds the full list and a copy action
 * per contact, so the cell stays compact without losing access to the values.
 */
export function ContactsCell({ value }: ContactsCellProps) {
  if (!Array.isArray(value) || value.length === 0) {
    return <EmptyCell />
  }

  return (
    <div className={CELL_WRAPPER}>
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger asChild>
            <Badge
              variant="secondary"
              className={cn(BADGE_BASE, 'cursor-default tabular-nums', BADGE_COLOR_CLASSES.slate)}
              tabIndex={0}
              aria-label={i18n.t('table.primaryContactsCount', { count: value.length })}
            >
              {value.length}
            </Badge>
          </TooltipTrigger>
          <TooltipContent side="top" variant="light" className="w-72 p-0">
            <div className="flex flex-col divide-y">
              {value.map((contact) => {
                const tint = contact.icon ? CONTACT_ICON_TINT[contact.icon] : undefined
                return (
                  <div
                    key={`${contact.type}-${contact.value}`}
                    className="flex items-start gap-2 px-3 py-2"
                  >
                    <span className="mt-0.5 shrink-0">
                      {enumIcon(contact.icon, cn('size-3.5', tint))}
                    </span>
                    <span className="flex min-w-0 flex-1 flex-col leading-tight">
                      <span className="flex items-center gap-1.5">
                        <Badge
                          variant="outline"
                          className="px-1.5 mb-1 py-0 text-[0.625rem] font-normal leading-tight"
                        >
                          {enumLabelOf('contact_type', contact.type)}
                        </Badge>
                        <span className="font-medium">{contact.label}</span>
                      </span>
                      <span className="truncate text-muted-foreground">{contact.value}</span>
                    </span>
                    <CopyButton value={contact.value} />
                  </div>
                )
              })}
            </div>
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    </div>
  )
}
