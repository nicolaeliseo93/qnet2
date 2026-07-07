import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateEaSectorPayload,
  EaSectorDetail,
  EaSectorDetailWithPermissions,
  EaSectorTreeNode,
  UpdateEaSectorPayload,
} from '@/features/ea-sectors/types'

/** Fetches the full sector tree (roots → descendants), used by the parent picker. */
export async function fetchEaSectorTree(): Promise<EaSectorTreeNode[]> {
  const { data } = await apiClient.get<ApiResponse<EaSectorTreeNode[]>>('/ea-sectors/tree')
  return data.data
}

/**
 * Fetches a single sector detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchEaSector(id: number): Promise<EaSectorDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<EaSectorDetail, ResourcePermissions>
  >(`/ea-sectors/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a sector. Returns the created resource from the envelope `data`. */
export async function createEaSector(payload: CreateEaSectorPayload): Promise<EaSectorDetail> {
  const { data } = await apiClient.post<ApiResponse<EaSectorDetail>>('/ea-sectors', payload)
  return data.data
}

/** Partially updates a sector (PATCH). Returns the updated resource. */
export async function updateEaSector(
  id: number,
  payload: UpdateEaSectorPayload,
): Promise<EaSectorDetail> {
  const { data } = await apiClient.patch<ApiResponse<EaSectorDetail>>(`/ea-sectors/${id}`, payload)
  return data.data
}

/** Deletes a sector. Backend responds 204 with no body (409 if it has children). */
export async function deleteEaSector(id: number): Promise<void> {
  await apiClient.delete(`/ea-sectors/${id}`)
}
