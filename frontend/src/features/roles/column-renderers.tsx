import { CountCell, DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`. The `permissions`
 * (count badge), `users_count` (count badge) and `created_at` (datetime) cells
 * reuse the shared, domain-agnostic table renderers; everything else falls back
 * to AG Grid defaults. The roles grid intentionally keeps the permissions cell
 * compact by showing only the number of permissions on the role.
 */
export const roleColumnRenderers: TableRendererMap = {
  permissions: (params) => (
    <CountCell
      {...params}
      value={Array.isArray(params.value) ? params.value.length : params.value}
    />
  ),
  users_count: (params) => <CountCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
