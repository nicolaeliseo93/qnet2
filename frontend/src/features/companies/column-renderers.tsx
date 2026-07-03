import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`. Only `created_at`
 * needs a custom renderer; the geo-derived columns (city/province/region/
 * postal_code/country) and the scalar fields (`denomination`/`vat_number`)
 * fall back to AG Grid defaults.
 */
export const companyColumnRenderers: TableRendererMap = {
  created_at: (params) => <DateTimeCell {...params} />,
}
