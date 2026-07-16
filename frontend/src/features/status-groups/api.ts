import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateStatusGroupPayload,
  StatusGroupDetail,
  StatusGroupDetailWithPermissions,
  UpdateStatusGroupPayload,
} from '@/features/status-groups/types'

/**
 * Fetches a single status group detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchStatusGroup(id: number): Promise<StatusGroupDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<StatusGroupDetail, ResourcePermissions>
  >(`/status-groups/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a status group. Returns the created resource from the envelope `data`. */
export async function createStatusGroup(
  payload: CreateStatusGroupPayload,
): Promise<StatusGroupDetail> {
  const { data } = await apiClient.post<ApiResponse<StatusGroupDetail>>('/status-groups', payload)
  return data.data
}

/** Partially updates a status group (PATCH). Returns the updated resource. */
export async function updateStatusGroup(
  id: number,
  payload: UpdateStatusGroupPayload,
): Promise<StatusGroupDetail> {
  const { data } = await apiClient.patch<ApiResponse<StatusGroupDetail>>(
    `/status-groups/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a status group. Backend responds 204 with no body, or 409 when the
 * group is still referenced by a status (spec 0039 D-6) — the caller
 * surfaces the backend's exact `message` for that case.
 */
export async function deleteStatusGroup(id: number): Promise<void> {
  await apiClient.delete(`/status-groups/${id}`)
}
