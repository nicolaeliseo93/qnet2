import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { fetchForSelect } from '@/features/for-select/api'
import type { ForSelectItem } from '@/features/for-select/types'
import type {
  ReorderedStatusEntry,
  StatusReorderItem,
  SystemStatusKey,
} from '@/features/status-reorder/types'

/** Page size for the one-shot reads below: statuses are a small lookup table, never paginated. */
const STATUS_PAGE_SIZE = 100

/** A for-select item extended with the `system_key` marker (spec 0039), not modeled on the generic `ForSelectItem`. */
interface StatusForSelectItem extends ForSelectItem {
  meta?: { system_key: SystemStatusKey }
}

/**
 * Persists a new custom-status order (spec 0039 D-5). `resource` is either
 * `pipeline-statuses` or `opportunity-statuses`; `orderedIds` must be EXACTLY the
 * custom (non-system) ids, in their new visual order — the backend rejects
 * anything else with 422. Returns the fresh, full ordered list.
 */
export async function reorderStatuses(
  resource: string,
  orderedIds: number[],
): Promise<ReorderedStatusEntry[]> {
  const { data } = await apiClient.post<ApiResponse<ReorderedStatusEntry[]>>(
    `/${resource}/reorder`,
    { ordered_ids: orderedIds },
  )
  return data.data
}

/**
 * Fetches the full ordered status list (system + custom) used to seed the
 * reorder sheet. Statuses are always ordered `sort_order,name,id` server-side,
 * so a single page covers the complete set — no pagination needed for this
 * one-shot read.
 */
export async function fetchStatusesForReorder(resource: string): Promise<StatusReorderItem[]> {
  const response = await fetchForSelect(resource, { limit: STATUS_PAGE_SIZE })
  const items = response.items as StatusForSelectItem[]
  return items.map((item) => ({
    id: item.id,
    name: item.label,
    systemKey: item.meta?.system_key ?? null,
  }))
}

/**
 * Resolves the id of a resource's system status (spec 0039 D-3), e.g. the
 * "Nuovo" status used to preselect it on create. "Nuovo" always sorts first
 * (`sort_order = 0`), so the first page is enough.
 */
export async function fetchSystemStatusId(
  resource: string,
  key: 'new' | 'closed',
): Promise<number | null> {
  const response = await fetchForSelect(resource, { limit: STATUS_PAGE_SIZE })
  const items = response.items as StatusForSelectItem[]
  return items.find((item) => item.meta?.system_key === key)?.id ?? null
}
