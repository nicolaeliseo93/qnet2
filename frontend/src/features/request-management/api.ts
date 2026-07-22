import { apiClient } from '@/api/client'
import type { ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  RequestWorkPanel,
  RequestWorkPanelWithPermissions,
  UpdateRequestWorkPayload,
} from '@/features/request-management/types'

/**
 * Fetches the work panel of a single opportunity together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchRequestWorkPanel(
  opportunityId: number,
): Promise<RequestWorkPanelWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<RequestWorkPanel, ResourcePermissions>
  >(`/request-management/${opportunityId}`)
  return { ...data.data, permissions: data.permissions }
}

/**
 * Partially updates the work panel (PATCH, sparse diff). Returns the updated
 * panel together with the actor's authorization metadata.
 */
export async function updateRequestWork(
  opportunityId: number,
  payload: UpdateRequestWorkPayload,
): Promise<RequestWorkPanelWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<RequestWorkPanel, ResourcePermissions>
  >(`/request-management/${opportunityId}`, payload)
  return { ...data.data, permissions: data.permissions }
}
