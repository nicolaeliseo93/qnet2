import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { ChevronRight } from 'lucide-react'
import { cn } from '@/lib/utils'
import { UserAvatar } from '@/components/user-avatar'
import { AvatarGroup, AvatarGroupCount } from '@/components/ui/avatar'
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card'
import { EmptyCell } from '@/features/table/cell-renderers'
import { useUserDetailSheet } from '@/features/users/user-detail-sheet-context'

/**
 * The shape every "person" column emits: an `{id, name}` summary, optionally
 * with an inlined avatar (`avatar_url`). Single-user columns (leads operator,
 * opportunities supervisor, users reports_to, business-functions manager) carry
 * one; multi-user columns (business-functions members) carry an array.
 */
export interface UserSummary {
  id: number
  name: string
  avatar_url?: string | null
}

/** How many avatars are shown inline before collapsing into a "+N" chip. */
const MAX_VISIBLE_AVATARS = 5

/** Compact avatar size inside grid cells (matches the other table avatars). */
const CELL_AVATAR_CLASS = 'size-6'

function toUser(value: unknown): UserSummary | null {
  const user = value as UserSummary | null | undefined
  if (!user || typeof user.id !== 'number' || typeof user.name !== 'string' || user.name === '') {
    return null
  }
  return user
}

/**
 * The clickable row shown inside a user's hover card: avatar + name + chevron.
 * Clicking (mouse or keyboard) opens the shared read-only user detail Sheet.
 */
function UserHoverAction({ user }: { user: UserSummary }) {
  const { t } = useTranslation()
  const { openUserDetail } = useUserDetailSheet()
  return (
    <button
      type="button"
      onClick={() => openUserDetail(user.id)}
      aria-label={t('common.viewProfile', { name: user.name })}
      className="flex w-full items-center gap-2 rounded-sm px-1.5 py-1 text-left text-sm outline-none hover:bg-accent focus-visible:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
    >
      <UserAvatar name={user.name} src={user.avatar_url ?? null} className="size-6 shrink-0" />
      <span className="truncate font-medium">{user.name}</span>
      <ChevronRight aria-hidden="true" className="ml-auto size-3.5 shrink-0 text-muted-foreground" />
    </button>
  )
}

/**
 * A single person chip: a hover card whose trigger (`children`) is also the
 * button that opens the user's detail Sheet on click/Enter — so the profile is
 * reachable by keyboard without depending on the hover card (a sighted-user
 * enhancement that reveals the same "open profile" action).
 */
function UserHoverCard({
  user,
  triggerClassName,
  children,
}: {
  user: UserSummary
  triggerClassName?: string
  children: ReactNode
}) {
  const { t } = useTranslation()
  const { openUserDetail } = useUserDetailSheet()
  return (
    <HoverCard>
      <HoverCardTrigger asChild>
        <button
          type="button"
          onClick={() => openUserDetail(user.id)}
          aria-label={t('common.viewProfile', { name: user.name })}
          className={cn(
            'flex items-center gap-2 overflow-hidden text-left outline-none focus-visible:ring-2 focus-visible:ring-ring',
            triggerClassName,
          )}
        >
          {children}
        </button>
      </HoverCardTrigger>
      <HoverCardContent align="start" className="w-auto min-w-56 max-w-72 p-1">
        <UserHoverAction user={user} />
      </HoverCardContent>
    </HoverCard>
  )
}

/**
 * A single-user column cell: an avatar + name, both a hover-card trigger and the
 * clickable surface that opens the user's detail Sheet. Em dash when empty.
 */
export function UserCell({ value }: ICellRendererParams) {
  const user = toUser(value)
  if (!user) {
    return <EmptyCell align="left" />
  }
  return (
    <div className="flex h-full items-center overflow-hidden">
      <UserHoverCard user={user} triggerClassName="rounded-md">
        <UserAvatar name={user.name} src={user.avatar_url ?? null} className={cn(CELL_AVATAR_CLASS, 'shrink-0')} />
        <span className="truncate">{user.name}</span>
      </UserHoverCard>
    </div>
  )
}

/**
 * A multi-user column cell: an overlapping avatar stack, one clickable hover
 * card per user, collapsing beyond `MAX_VISIBLE_AVATARS` into a "+N" chip whose
 * card lists the remaining users (each clickable). Em dash when empty.
 */
export function UserStackCell({ value }: ICellRendererParams) {
  const users = Array.isArray(value) ? value.map(toUser).filter((u): u is UserSummary => u !== null) : []
  if (users.length === 0) {
    return <EmptyCell align="left" />
  }

  const visible = users.slice(0, MAX_VISIBLE_AVATARS)
  const overflow = users.slice(MAX_VISIBLE_AVATARS)

  return (
    <div className="flex h-full items-center">
      <AvatarGroup>
        {visible.map((user) => (
          <UserHoverCard key={user.id} user={user} triggerClassName="rounded-full">
            <UserAvatar name={user.name} src={user.avatar_url ?? null} className={CELL_AVATAR_CLASS} />
          </UserHoverCard>
        ))}
        {overflow.length > 0 && (
          <HoverCard>
            <HoverCardTrigger asChild>
              <AvatarGroupCount
                tabIndex={0}
                className={cn(CELL_AVATAR_CLASS, 'cursor-default outline-none focus-visible:ring-2 focus-visible:ring-ring')}
                aria-label={overflow.map((user) => user.name).join(', ')}
              >
                +{overflow.length}
              </AvatarGroupCount>
            </HoverCardTrigger>
            <HoverCardContent align="start" className="max-h-64 w-auto min-w-56 max-w-72 overflow-y-auto p-1">
              <ul className="flex flex-col gap-0.5">
                {overflow.map((user) => (
                  <li key={user.id}>
                    <UserHoverAction user={user} />
                  </li>
                ))}
              </ul>
            </HoverCardContent>
          </HoverCard>
        )}
      </AvatarGroup>
    </div>
  )
}
