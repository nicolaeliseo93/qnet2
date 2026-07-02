import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  CreateUserPayload,
  UpdateUserPayload,
  UserDetail,
} from '@/features/users/types'

/** Fetches a single user detail. Wrapped in the standard envelope → `data`. */
export async function fetchUser(id: number): Promise<UserDetail> {
  const { data } = await apiClient.get<ApiResponse<UserDetail>>(`/users/${id}`)
  return data.data
}

/** Creates a user. Returns the created resource from the envelope `data`. */
export async function createUser(
  payload: CreateUserPayload,
): Promise<UserDetail> {
  const { data } = await apiClient.post<ApiResponse<UserDetail>>(
    '/users',
    payload,
  )
  return data.data
}

/** Partially updates a user (PATCH). Returns the updated resource. */
export async function updateUser(
  id: number,
  payload: UpdateUserPayload,
): Promise<UserDetail> {
  const { data } = await apiClient.patch<ApiResponse<UserDetail>>(
    `/users/${id}`,
    payload,
  )
  return data.data
}

/** Deletes a user. Backend responds 204 with no body. */
export async function deleteUser(id: number): Promise<void> {
  await apiClient.delete(`/users/${id}`)
}

/** Uploads a user's avatar (multipart). Returns the updated resource. */
export async function uploadUserAvatar(
  id: number,
  file: File,
): Promise<UserDetail> {
  const formData = new FormData()
  formData.append('avatar', file)
  const { data } = await apiClient.post<ApiResponse<UserDetail>>(
    `/users/${id}/avatar`,
    formData,
  )
  return data.data
}

/** Removes a user's avatar. Returns the updated resource. */
export async function deleteUserAvatar(id: number): Promise<UserDetail> {
  const { data } = await apiClient.delete<ApiResponse<UserDetail>>(
    `/users/${id}/avatar`,
  )
  return data.data
}
