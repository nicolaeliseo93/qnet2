import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateLeadStatusPayload,
  LeadStatusDetail,
  LeadStatusDetailWithPermissions,
  UpdateLeadStatusPayload,
} from '@/features/lead-statuses/types'

/**
 * Fetches a single lead status detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchLeadStatus(id: number): Promise<LeadStatusDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<LeadStatusDetail, ResourcePermissions>
  >(`/lead-statuses/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a lead status. Returns the created resource from the envelope `data`. */
export async function createLeadStatus(
  payload: CreateLeadStatusPayload,
): Promise<LeadStatusDetail> {
  const { data } = await apiClient.post<ApiResponse<LeadStatusDetail>>('/lead-statuses', payload)
  return data.data
}

/** Partially updates a lead status (PATCH). Returns the updated resource. */
export async function updateLeadStatus(
  id: number,
  payload: UpdateLeadStatusPayload,
): Promise<LeadStatusDetail> {
  const { data } = await apiClient.patch<ApiResponse<LeadStatusDetail>>(
    `/lead-statuses/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a lead status. Backend responds 204 with no body, or 409 when the
 * status is still referenced by a Lead (BR-3) — the caller surfaces the
 * backend's `message` for that case.
 */
export async function deleteLeadStatus(id: number): Promise<void> {
  await apiClient.delete(`/lead-statuses/${id}`)
}
