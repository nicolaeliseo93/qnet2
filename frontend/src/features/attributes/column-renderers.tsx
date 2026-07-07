import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`. `code`/`name` fall
 * back to the AG Grid default text cell; `data_type` is a `badge` column and
 * renders through the generic `BadgeCell` fallback (backend-supplied
 * badges + `enumKey: 'attribute_type'`), so no domain renderer is needed for
 * it. `created_at` reuses the shared domain-agnostic renderer (mirrors
 * `referentTypeColumnRenderers`).
 */
export const attributeColumnRenderers: TableRendererMap = {
  created_at: (params) => <DateTimeCell {...params} />,
}
