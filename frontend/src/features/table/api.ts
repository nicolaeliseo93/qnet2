import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  ColumnPreferenceInput,
  TableConfig,
  TableRowsPayload,
  TableRowsResponse,
} from '@/features/table/types'

/**
 * Fetches a domain's table schema (columns, filters, action catalog, defaults).
 * Wrapped in the standard `ok()` envelope → config lives in `data`.
 * One endpoint serves every domain; the `{domain}` segment selects the schema.
 */
export async function fetchTableConfig(domain: string): Promise<TableConfig> {
  const { data } = await apiClient.get<ApiResponse<TableConfig>>(
    `/tables/${domain}/columns`,
  )
  return data.data
}

/**
 * Fetches a block of rows for the Server-Side Row Model of a given domain. Uses
 * POST because the SSRM payload (sort/filter models) is nested. Returns the
 * `paginatedResponse()` envelope directly (items + pagination), not wrapped in
 * `data`.
 */
export async function fetchTableRows(
  domain: string,
  payload: TableRowsPayload,
): Promise<TableRowsResponse> {
  const { data } = await apiClient.post<TableRowsResponse>(
    `/tables/${domain}/rows`,
    payload,
  )
  return data
}

/**
 * Persists the current user's column layout (order/width/visibility) for a
 * domain. Self-scoped server-side (the user_id is never sent). Returns the
 * freshly merged config so the cache can stay in sync. See 0003.
 */
export async function saveTablePreferences(
  domain: string,
  columns: ColumnPreferenceInput[],
): Promise<TableConfig> {
  const { data } = await apiClient.post<ApiResponse<TableConfig>>(
    `/tables/${domain}/preferences`,
    { columns },
  )
  return data.data
}

/**
 * Resets the current user's column layout for a domain to the PHP default by
 * deleting the saved row (explicit user action; nothing else clears it). See
 * 0003.
 */
export async function resetTablePreferences(domain: string): Promise<void> {
  await apiClient.delete(`/tables/${domain}/preferences`)
}
