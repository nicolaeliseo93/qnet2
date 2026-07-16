import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { ActivityLogPage } from '@/features/activity-log/types'

/** Default page size requested when the caller does not override it. */
export const ACTIVITY_LOG_DEFAULT_PAGE_SIZE = 25

/**
 * Fetches one keyset page of the aggregated activity log for a resource
 * record (spec 0034). `cursor` is the opaque token from the previous page's
 * `next_cursor`; omit it for the first page.
 */
export async function fetchActivityLog(
  resource: string,
  id: number,
  cursor: string | null = null,
  perPage: number = ACTIVITY_LOG_DEFAULT_PAGE_SIZE,
): Promise<ActivityLogPage> {
  const { data } = await apiClient.get<ApiResponse<ActivityLogPage>>(
    `/activity-log/${resource}/${id}`,
    { params: { cursor: cursor ?? undefined, per_page: perPage } },
  )
  return data.data
}
