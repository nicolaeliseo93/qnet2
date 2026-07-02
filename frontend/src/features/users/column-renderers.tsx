/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { UserAvatar } from '@/components/user-avatar'
import {
  ContactsCell,
  DateTimeCell,
  TagsCell,
} from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Renders an email as a mailto link (users-specific). */
function EmailCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <span className="text-muted-foreground">—</span>
  }
  return (
    <a className="text-primary underline-offset-4 hover:underline" href={`mailto:${value}`}>
      {value}
    </a>
  )
}

/**
 * Renders the user's avatar with an initials fallback. The cell value is the
 * avatar image inlined as a data: URI (or null); the row's name drives the
 * fallback initials.
 */
function AvatarCell({ value, data }: ICellRendererParams) {
  const src = typeof value === 'string' && value !== '' ? value : null
  const name = typeof data?.name === 'string' ? data.name : ''

  return (
    <div className="flex h-full items-center">
      <UserAvatar name={name} src={src} size="sm" />
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id`. Only columns that need
 * special rendering appear here; everything else falls back to AG Grid defaults.
 * The generic `roles` (tags) and `created_at` (datetime) cells come from the
 * shared table renderers so they are not re-implemented per domain.
 */
export const userColumnRenderers: TableRendererMap = {
  avatar_url: (params) => <AvatarCell {...params} />,
  roles: (params) => <TagsCell {...params} />,
  email: (params) => <EmailCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  // All primary contacts (one per type): icon + label badges, value in tooltip.
  primary_contact: (params) => <ContactsCell {...params} />,
}
