import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateOpportunityStatusPayload,
  OpportunityStatusDetail,
  OpportunityStatusDetailWithPermissions,
  UpdateOpportunityStatusPayload,
} from '@/features/opportunity-statuses/types'

/**
 * Fetches a single opportunity status detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchOpportunityStatus(id: number): Promise<OpportunityStatusDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<OpportunityStatusDetail, ResourcePermissions>
  >(`/opportunity-statuses/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates an opportunity status. Returns the created resource from the envelope `data`. */
export async function createOpportunityStatus(
  payload: CreateOpportunityStatusPayload,
): Promise<OpportunityStatusDetail> {
  const { data } = await apiClient.post<ApiResponse<OpportunityStatusDetail>>(
    '/opportunity-statuses',
    payload,
  )
  return data.data
}

/** Partially updates an opportunity status (PATCH). Returns the updated resource. */
export async function updateOpportunityStatus(
  id: number,
  payload: UpdateOpportunityStatusPayload,
): Promise<OpportunityStatusDetail> {
  const { data } = await apiClient.patch<ApiResponse<OpportunityStatusDetail>>(
    `/opportunity-statuses/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes an opportunity status. Backend responds 204 with no body, or 409
 * when the status is still referenced by an Opportunity (BR-4) — the caller
 * surfaces the backend's exact `message` for that case.
 */
export async function deleteOpportunityStatus(id: number): Promise<void> {
  await apiClient.delete(`/opportunity-statuses/${id}`)
}
