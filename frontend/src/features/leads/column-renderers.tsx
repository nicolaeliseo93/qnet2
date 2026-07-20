/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import { Building2, MapPin, Megaphone, Radio } from 'lucide-react'
import { DateTimeCell } from '@/features/table/cell-renderers'
import { RelationCell } from '@/features/table/rich-cells'
import { UserCell } from '@/features/table/user-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0024), built
 * from the shared cross-module cell library so relations, the status pill and
 * the boolean badges match the projects/campaigns grids. `operator` is a person,
 * so it renders as the shared user cell (avatar + name, click to open the user
 * detail Sheet); `operational_site` has no `name`
 * (BR-3), only the server-composed `label`, which `RelationCell` reads. `notes`
 * falls back to the AG Grid default cell; `created_at` reuses the shared
 * datetime renderer.
 */
export const leadColumnRenderers: TableRendererMap = {
  registry: (params) => <RelationCell {...params} icon={Building2} />,
  campaign: (params) => <RelationCell {...params} icon={Megaphone} />,
  operational_site: (params) => <RelationCell {...params} icon={MapPin} />,
  source: (params) => <RelationCell {...params} icon={Radio} />,
  operator: (params) => <UserCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
