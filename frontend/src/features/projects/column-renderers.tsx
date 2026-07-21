import { Boxes, Briefcase, Handshake, MapPin } from 'lucide-react'
import { DateTimeCell } from '@/features/table/cell-renderers'
import {
  CodeBadgeCell,
  CurrencyCell,
  DateCell,
  GeoScopeCell,
  RelationCell,
  StatusBadgeCell,
} from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`, built entirely from
 * the shared cross-module cell library (`features/table/rich-cells`) so a
 * relation, a status, a date or a money value looks identical here and in the
 * campaigns/leads grids. `code` renders as a compact monospace badge; `name`/
 * `target_lead` fall back to the AG Grid default cell; `created_at` reuses the
 * shared datetime renderer (spec 0023).
 * The geo relations (`country`/`state`/`province`/`city`, spec 0027) stay
 * icon-free — the column headers already name them and four map pins in a row
 * would be noise; `geo_scope` is the DISPLAY-ONLY derived badge (D-2).
 */
export const projectColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  pipeline_status: (params) => <StatusBadgeCell {...params} />,
  business_function: (params) => <RelationCell {...params} icon={Briefcase} />,
  country: (params) => <RelationCell {...params} />,
  state: (params) => <RelationCell {...params} />,
  province: (params) => <RelationCell {...params} />,
  city: (params) => <RelationCell {...params} />,
  geo_scope: (params) => <GeoScopeCell {...params} />,
  product_category: (params) => <RelationCell {...params} icon={Boxes} />,
  partner: (params) => <RelationCell {...params} icon={Handshake} />,
  operational_site: (params) => <RelationCell {...params} icon={MapPin} />,
  start_date: (params) => <DateCell {...params} />,
  end_date: (params) => <DateCell {...params} />,
  total_budget: (params) => <CurrencyCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
