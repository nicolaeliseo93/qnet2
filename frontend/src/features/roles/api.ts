import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  CreateRolePayload,
  RoleDetail,
  UpdateRolePayload,
} from '@/features/roles/types'

/** Fetches a single role detail. Wrapped in the standard envelope → `data`. */
export async function fetchRole(id: number): Promise<RoleDetail> {
  const { data } = await apiClient.get<ApiResponse<RoleDetail>>(`/roles/${id}`)
  return data.data
}

/** Creates a role. Returns the created resource from the envelope `data`. */
export async function createRole(
  payload: CreateRolePayload,
): Promise<RoleDetail> {
  const { data } = await apiClient.post<ApiResponse<RoleDetail>>(
    '/roles',
    payload,
  )
  return data.data
}

/** Partially updates a role (PATCH). Returns the updated resource. */
export async function updateRole(
  id: number,
  payload: UpdateRolePayload,
): Promise<RoleDetail> {
  const { data } = await apiClient.patch<ApiResponse<RoleDetail>>(
    `/roles/${id}`,
    payload,
  )
  return data.data
}

/** Deletes a role. Backend responds 204 with no body. */
export async function deleteRole(id: number): Promise<void> {
  await apiClient.delete(`/roles/${id}`)
}
