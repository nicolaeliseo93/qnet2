import type { CellRenderer } from '@/components/data-table/data-table'

/**
 * Client-side renderer map for a single domain: `columnId → CellRenderer`.
 *
 * The generic table accepts this map via props and applies a custom renderer
 * wherever a column id matches; columns without an entry fall back to the
 * agnostic AG Grid defaults. The generic table NEVER knows which domains exist —
 * each domain owns its own map and passes it down from its thin adapter.
 *
 * Example (domain side):
 *   export const userColumnRenderers: TableRendererMap = {
 *     roles: (params) => <RolesCell {...params} />,
 *   }
 */
export type TableRendererMap = Record<string, CellRenderer>
