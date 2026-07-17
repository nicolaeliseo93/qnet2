import { Building2, FolderKanban, Radio } from 'lucide-react'
import { DateTimeCell } from '@/features/table/cell-renderers'
import {
  CurrencyCell,
  DateCell,
  GeoScopeCell,
  RelationCell,
} from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`, built from the shared
 * cross-module cell library so the campaign grid matches projects/leads. Unlike
 * `projects.pipeline_status`, the campaign `pipeline_status` is resolved
 * name-only server-side (no color token via `CampaignsTableDefinition::mapRow`),
 * so it renders as a plain relation, not a colored status badge. `code`/`name`/
 * `target_lead` fall back to the AG Grid default cell; `created_at` reuses the
 * shared datetime renderer (spec 0023). `geo_scope` is the merged, display-only
 * campaign-or-project geo badge with its resolved place name (spec 0027 D-2).
 */
export const campaignColumnRenderers: TableRendererMap = {
  project: (params) => <RelationCell {...params} icon={FolderKanban} />,
  registry: (params) => <RelationCell {...params} icon={Building2} />,
  pipeline_status: (params) => <RelationCell {...params} />,
  source: (params) => <RelationCell {...params} icon={Radio} />,
  country: (params) => <RelationCell {...params} />,
  state: (params) => <RelationCell {...params} />,
  province: (params) => <RelationCell {...params} />,
  city: (params) => <RelationCell {...params} />,
  geo_scope: (params) => <GeoScopeCell {...params} withPlace />,
  start_date: (params) => <DateCell {...params} />,
  end_date: (params) => <DateCell {...params} />,
  total_budget: (params) => <CurrencyCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
