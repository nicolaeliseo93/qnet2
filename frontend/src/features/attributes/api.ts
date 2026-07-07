import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  AttributeDetail,
  AttributeDetailWithPermissions,
  CreateAttributePayload,
  UpdateAttributePayload,
} from '@/features/attributes/types'

/**
 * Fetches a single attribute detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchAttribute(id: number): Promise<AttributeDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<AttributeDetail, ResourcePermissions>
  >(`/attributes/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates an attribute. Returns the created resource from the envelope `data`. */
export async function createAttribute(
  payload: CreateAttributePayload,
): Promise<AttributeDetail> {
  const { data } = await apiClient.post<ApiResponse<AttributeDetail>>('/attributes', payload)
  return data.data
}

/** Partially updates an attribute (PATCH). Returns the updated resource. */
export async function updateAttribute(
  id: number,
  payload: UpdateAttributePayload,
): Promise<AttributeDetail> {
  const { data } = await apiClient.patch<ApiResponse<AttributeDetail>>(
    `/attributes/${id}`,
    payload,
  )
  return data.data
}

/** Deletes an attribute. Backend responds 204 with no body. */
export async function deleteAttribute(id: number): Promise<void> {
  await apiClient.delete(`/attributes/${id}`)
}
