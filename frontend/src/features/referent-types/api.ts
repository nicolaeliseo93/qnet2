import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateReferentTypePayload,
  ReferentTypeDetail,
  ReferentTypeDetailWithPermissions,
  UpdateReferentTypePayload,
} from '@/features/referent-types/types'

/**
 * Fetches a single referent-type detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchReferentType(
  id: number,
): Promise<ReferentTypeDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<ReferentTypeDetail, ResourcePermissions>
  >(`/referent-types/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a referent type. Returns the created resource from the envelope `data`. */
export async function createReferentType(
  payload: CreateReferentTypePayload,
): Promise<ReferentTypeDetail> {
  const { data } = await apiClient.post<ApiResponse<ReferentTypeDetail>>(
    '/referent-types',
    payload,
  )
  return data.data
}

/** Partially updates a referent type (PATCH). Returns the updated resource. */
export async function updateReferentType(
  id: number,
  payload: UpdateReferentTypePayload,
): Promise<ReferentTypeDetail> {
  const { data } = await apiClient.patch<ApiResponse<ReferentTypeDetail>>(
    `/referent-types/${id}`,
    payload,
  )
  return data.data
}

/** Deletes a referent type. Backend responds 204 with no body. */
export async function deleteReferentType(id: number): Promise<void> {
  await apiClient.delete(`/referent-types/${id}`)
}
