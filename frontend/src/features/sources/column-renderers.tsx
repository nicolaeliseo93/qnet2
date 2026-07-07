import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`. `name` falls back
 * to the AG Grid default text cell; `created_at` reuses the shared
 * domain-agnostic renderer so the datetime formatting is not re-implemented
 * per domain (spec 0018, mirrors `referentTypeColumnRenderers`).
 */
export const sourceColumnRenderers: TableRendererMap = {
  created_at: (params) => <DateTimeCell {...params} />,
}
