/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { UserAvatar } from '@/components/user-avatar'
import { Badge } from '@/components/ui/badge'
import { ContactsCell, DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Renders the `is_default` column as a "Default" badge, blank otherwise (AC-015). */
function DefaultBadgeCell({ value }: ICellRendererParams) {
  return value === true ? (
    <div className="flex h-full items-center justify-center">
      <Badge variant="secondary">{i18n.t('companySites.columns.defaultBadge')}</Badge>
    </div>
  ) : (
    <span className="text-muted-foreground">—</span>
  )
}

/**
 * Renders the site's logo with an initials fallback (mirrors the Users
 * `AvatarCell`). The cell value is the logo image inlined as a data: URI (or
 * null); the row's name drives the fallback initials.
 */
function LogoCell({ value, data }: ICellRendererParams) {
  const src = typeof value === 'string' && value !== '' ? value : null
  const name = typeof data?.name === 'string' ? data.name : ''

  return (
    <div className="flex h-full items-center">
      <UserAvatar name={name} src={src} size="sm" />
    </div>
  )
}

/** Renders the derived `company` column: the hydrated company name, or an em dash. */
function CompanyCell({ value }: ICellRendererParams) {
  const company = value as { id: number; name: string } | null | undefined
  if (!company) {
    return <span className="text-muted-foreground">—</span>
  }
  return <span>{company.name}</span>
}

/**
 * Custom cell renderers keyed by the backend column `id`. `is_default`,
 * `primary_contact` (the card's primary contacts, shared `PrimaryContactColumn`
 * → compact count + tooltip), `logo_url`, `company` and `created_at` need
 * special rendering; every other backend-declared column (`name`, the
 * geo-derived city/province/region, postal_code) falls back to the AG Grid
 * default cell.
 */
export const companySiteColumnRenderers: TableRendererMap = {
  is_default: (params) => <DefaultBadgeCell {...params} />,
  primary_contact: (params) => <ContactsCell {...params} />,
  logo_url: (params) => <LogoCell {...params} />,
  company: (params) => <CompanyCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
