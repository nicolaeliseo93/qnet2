/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
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
 * Custom cell renderers keyed by the backend column `id`. `is_default`,
 * `primary_contact` (the card's primary contacts, shared `PrimaryContactColumn`
 * → compact count + tooltip) and `created_at` need special rendering; every
 * other backend-declared column (`name`, the geo-derived city/province/region,
 * postal_code) falls back to the AG Grid default cell.
 */
export const companySiteColumnRenderers: TableRendererMap = {
  is_default: (params) => <DefaultBadgeCell {...params} />,
  primary_contact: (params) => <ContactsCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
