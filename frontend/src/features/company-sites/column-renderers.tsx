/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import { DateTimeCell } from '@/features/table/cell-renderers'
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
 * Custom cell renderers keyed by the backend column `id`. `city`/`province`
 * (geo-derived) and the scalar fields (`name`/`email`/`vat_number`/`phone`)
 * fall back to AG Grid defaults.
 */
export const companySiteColumnRenderers: TableRendererMap = {
  is_default: (params) => <DefaultBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
