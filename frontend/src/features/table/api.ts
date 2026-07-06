import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  BulkDeleteResult,
  ColumnPreferenceInput,
  TableColumnValuesPayload,
  TableColumnValuesResponse,
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

/**
 * Persists the current user's applied AG Grid filterModel for a domain, so
 * filters survive a reload. Self-scoped server-side (the user_id is never sent);
 * an empty model clears the saved filters. Returns the freshly merged config so
 * the cache can stay in sync.
 */
export async function saveTableFilters(
  domain: string,
  filterModel: Record<string, unknown>,
): Promise<TableConfig> {
  const { data } = await apiClient.post<ApiResponse<TableConfig>>(
    `/tables/${domain}/filters`,
    { filterModel },
  )
  return data.data
}

/**
 * Resets the current user's saved filters for a domain by deleting the saved row
 * (explicit user action; nothing else clears it).
 */
export async function resetTableFilters(domain: string): Promise<void> {
  await apiClient.delete(`/tables/${domain}/filters`)
}

/**
 * Deletes a batch of rows for a domain in one request (currently-selected ids
 * only; the caller decides which ids are selected, never a server-side
 * "select all"). The backend re-checks authorization per row and reports any
 * id it could not delete instead of failing the whole request.
 */
export async function bulkDeleteTableRows(
  domain: string,
  ids: number[],
): Promise<BulkDeleteResult> {
  const { data } = await apiClient.post<ApiResponse<BulkDeleteResult>>(
    `/tables/${domain}/bulk-delete`,
    { ids },
  )
  return data.data
}

/**
 * Fetches the distinct values of one column for its Set Filter, restricted by
 * the filters currently active on the OTHER columns (Excel-like behavior; see
 * 0004). Called directly from the Set Filter's async `values` callback, not
 * through TanStack Query — AG Grid owns that callback's lifecycle.
 */
export async function fetchTableColumnValues(
  domain: string,
  payload: TableColumnValuesPayload,
): Promise<TableColumnValuesResponse> {
  const { data } = await apiClient.post<ApiResponse<TableColumnValuesResponse>>(
    `/tables/${domain}/values`,
    payload,
  )
  return data.data
}
