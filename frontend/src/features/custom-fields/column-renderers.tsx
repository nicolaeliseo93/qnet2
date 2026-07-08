/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import appI18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * `type` badge renderer for the `custom-fields` admin grid. The backend's
 * `badgesFor()` (CustomFieldsTableDefinition) supplies each badge's `label`
 * as an i18n KEY (`customFields.types.<type>`), not a localized string, and
 * does not declare an `enumKey` (spec's type catalogue is config-driven, not
 * a PHP enum) — so the generic `BadgeCell` fallback would render the raw key
 * verbatim. This renderer translates it directly from the row's `type` value,
 * reading `i18n` non-reactively (mirrors `enumLabelOf`/`column-defaults.tsx`'s
 * module-level cell renderers, which cannot call hooks).
 */
function TypeBadgeCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <span className="text-muted-foreground/60">—</span>
  }
  return (
    <div className="flex h-full w-full items-center justify-center px-2 py-1">
      <Badge variant="secondary" className="h-5 min-h-5">
        {appI18n.t(`customFields.types.${value}`)}
      </Badge>
    </div>
  )
}

export const customFieldColumnRenderers: TableRendererMap = {
  type: (params) => <TypeBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
