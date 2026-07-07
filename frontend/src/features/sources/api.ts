import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateSourcePayload,
  SourceDetail,
  SourceDetailWithPermissions,
  UpdateSourcePayload,
} from '@/features/sources/types'

/**
 * Fetches a single source detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchSource(id: number): Promise<SourceDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<SourceDetail, ResourcePermissions>
  >(`/sources/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a source. Returns the created resource from the envelope `data`. */
export async function createSource(payload: CreateSourcePayload): Promise<SourceDetail> {
  const { data } = await apiClient.post<ApiResponse<SourceDetail>>('/sources', payload)
  return data.data
}

/** Partially updates a source (PATCH). Returns the updated resource. */
export async function updateSource(
  id: number,
  payload: UpdateSourcePayload,
): Promise<SourceDetail> {
  const { data } = await apiClient.patch<ApiResponse<SourceDetail>>(`/sources/${id}`, payload)
  return data.data
}

/** Deletes a source. Backend responds 204 with no body. */
export async function deleteSource(id: number): Promise<void> {
  await apiClient.delete(`/sources/${id}`)
}
