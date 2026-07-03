import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateOperationalSitePayload,
  OperationalSiteDetail,
  OperationalSiteDetailWithPermissions,
  UpdateOperationalSitePayload,
} from '@/features/operational-sites/types'

/**
 * Fetches a single operational site detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchOperationalSite(
  id: number,
): Promise<OperationalSiteDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<OperationalSiteDetail, ResourcePermissions>
  >(`/operational-sites/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates an operational site (and its single primary address). Returns the created resource from the envelope `data`. */
export async function createOperationalSite(
  payload: CreateOperationalSitePayload,
): Promise<OperationalSiteDetail> {
  const { data } = await apiClient.post<ApiResponse<OperationalSiteDetail>>(
    '/operational-sites',
    payload,
  )
  return data.data
}

/** Partially updates an operational site (PATCH). Returns the updated resource. */
export async function updateOperationalSite(
  id: number,
  payload: UpdateOperationalSitePayload,
): Promise<OperationalSiteDetail> {
  const { data } = await apiClient.patch<ApiResponse<OperationalSiteDetail>>(
    `/operational-sites/${id}`,
    payload,
  )
  return data.data
}

/** Deletes an operational site. Backend responds 204 with no body. */
export async function deleteOperationalSite(id: number): Promise<void> {
  await apiClient.delete(`/operational-sites/${id}`)
}
