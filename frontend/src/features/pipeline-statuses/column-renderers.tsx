/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { DateTimeCell } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { StatusGroupRef } from '@/features/status-reorder/types'

/** Renders the `color` column as a swatch dot + localized token name. */
function ColorCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  const token = typeof value === 'string' ? value : null

  if (!token) {
    return <span className="flex h-full items-center px-2 text-muted-foreground/60">—</span>
  }

  const swatch = swatchClassFor(token)

  return (
    <div className="flex h-full items-center gap-2 px-2">
      <span
        className={cn('size-3 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
        aria-hidden="true"
      />
      <span className="truncate">{t(`customFields.colors.${token}`)}</span>
    </div>
  )
}

/** Renders the derived `status_group` column as a colored badge, or an empty dash when unset (spec 0039 D-6/D-7). */
function StatusGroupCell({ value }: ICellRendererParams) {
  const statusGroup = value as StatusGroupRef | null | undefined

  if (!statusGroup) {
    return <span className="flex h-full items-center px-2 text-muted-foreground/60">—</span>
  }

  const swatch = swatchClassFor(statusGroup.color)

  return (
    <div className="flex h-full items-center gap-2 px-2">
      <span
        className={cn('size-3 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
        aria-hidden="true"
      />
      <span className="truncate">{statusGroup.name}</span>
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id`. `name` and
 * `sort_order` fall back to the AG Grid default cells; `color` renders a
 * swatch (the backend column type is plain `text`, a raw palette token);
 * `status_group` renders the embedded `{id,name,color}` object as a badge
 * (spec 0039, derived column); `created_at` reuses the shared
 * domain-agnostic renderer so the datetime formatting is not re-implemented
 * per domain (spec 0023, mirrors `sourceColumnRenderers`).
 */
export const pipelineStatusColumnRenderers: TableRendererMap = {
  color: (params) => <ColorCell {...params} />,
  status_group: (params) => <StatusGroupCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
