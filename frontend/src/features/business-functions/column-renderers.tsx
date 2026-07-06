/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { Check, X } from 'lucide-react'
import i18n from '@/i18n'
import { UserAvatar } from '@/components/user-avatar'
import { Badge } from '@/components/ui/badge'
import { AvatarGroup, AvatarGroupCount } from '@/components/ui/avatar'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { BusinessFunctionMember } from '@/features/business-functions/types'

/** Hoisted empty-array default: a stable reference avoids a new array per render. */
const EMPTY_MEMBERS: BusinessFunctionMember[] = []

/** How many avatars are shown inline before collapsing into a “+N” chip. */
const MAX_VISIBLE_AVATARS = 5

/**
 * Compact avatar size used inside grid cells — matches the Users table avatar
 * column (`UserAvatar size="sm"` = `size-6`), kept as a class so we never touch
 * the untyped `size` prop.
 */
const CELL_AVATAR_CLASS = 'size-6'

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full items-center justify-center">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/**
 * A single avatar wrapped in an accessible hover/focus tooltip revealing the
 * member's name (AC-015). Shared by the `manager` (single avatar) and `users`
 * (avatar stack) cells so the tooltip composition lives in exactly one place.
 * Module-scoped (not nested in a render function) so it keeps a stable
 * component identity across re-renders.
 */
function AvatarWithTooltip({ member }: { member: BusinessFunctionMember }) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span
          tabIndex={0}
          aria-label={member.name}
          className="inline-flex rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <UserAvatar name={member.name} src={member.avatar_url} className={CELL_AVATAR_CLASS} />
        </span>
      </TooltipTrigger>
      <TooltipContent side="top">{member.name}</TooltipContent>
    </Tooltip>
  )
}

/**
 * Renders the `manager` column: a single avatar with a name tooltip, or an
 * em dash when the business function has no responsabile.
 */
function ManagerCell({ value }: ICellRendererParams) {
  const member = value as BusinessFunctionMember | null

  if (!member) {
    return <EmptyCell />
  }

  return (
    <div className="flex h-full items-center">
      <TooltipProvider>
        <AvatarWithTooltip member={member} />
      </TooltipProvider>
    </div>
  )
}

/**
 * Renders the `users` column: an overlapping avatar stack (one per associated
 * user), each with its own name tooltip. Caps the visible avatars at
 * `MAX_VISIBLE_AVATARS` and collapses the rest into a “+N” chip whose tooltip
 * lists the remaining names. Em dash when no user is associated.
 */
function UsersCell({ value }: ICellRendererParams) {
  const members = Array.isArray(value) ? (value as BusinessFunctionMember[]) : EMPTY_MEMBERS

  if (members.length === 0) {
    return <EmptyCell />
  }

  const visibleMembers = members.slice(0, MAX_VISIBLE_AVATARS)
  const overflowMembers = members.slice(MAX_VISIBLE_AVATARS)
  const overflowNames = overflowMembers.map((member) => member.name).join(', ')

  return (
    <div className="flex h-full items-center">
      <TooltipProvider>
        <AvatarGroup>
          {visibleMembers.map((member) => (
            <AvatarWithTooltip key={member.id} member={member} />
          ))}
          {overflowMembers.length > 0 && (
            <Tooltip>
              <TooltipTrigger asChild>
                <AvatarGroupCount
                  tabIndex={0}
                  className={CELL_AVATAR_CLASS}
                  aria-label={overflowNames}
                >
                  +{overflowMembers.length}
                </AvatarGroupCount>
              </TooltipTrigger>
              <TooltipContent side="top">{overflowNames}</TooltipContent>
            </Tooltip>
          )}
        </AvatarGroup>
      </TooltipProvider>
    </div>
  )
}

/**
 * Colored tone classes for the boolean badge (green = yes, red = no), reusing
 * the same palette as the generic table BadgeCell so tones stay consistent
 * across the app and adapt to dark mode.
 */
const BOOLEAN_BADGE_YES = 'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200'
const BOOLEAN_BADGE_NO = 'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200'

/**
 * Renders a boolean column (`is_business_unit`/`is_business_service`) as a
 * colored Sì/No badge with a leading icon (check for yes, cross for no). Color
 * is not the only signal — icon plus text keep it accessible.
 */
function BooleanCell({ value }: ICellRendererParams) {
  const isTrue = value === true

  return (
    <div className="flex h-full items-center justify-center">
      <Badge className={isTrue ? BOOLEAN_BADGE_YES : BOOLEAN_BADGE_NO}>
        {isTrue ? <Check aria-hidden="true" /> : <X aria-hidden="true" />}
        {isTrue ? i18n.t('common.yes') : i18n.t('common.no')}
      </Badge>
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id`. Only columns that
 * need special rendering appear here; `name` falls back to the AG Grid
 * default text cell. `created_at` reuses the shared domain-agnostic renderer
 * so the datetime formatting is not re-implemented per domain.
 */
export const businessFunctionColumnRenderers: TableRendererMap = {
  is_business_unit: (params) => <BooleanCell {...params} />,
  is_business_service: (params) => <BooleanCell {...params} />,
  manager: (params) => <ManagerCell {...params} />,
  users: (params) => <UsersCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
