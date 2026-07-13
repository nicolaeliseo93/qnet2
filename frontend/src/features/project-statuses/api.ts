import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateProjectStatusPayload,
  ProjectStatusDetail,
  ProjectStatusDetailWithPermissions,
  UpdateProjectStatusPayload,
} from '@/features/project-statuses/types'

/**
 * Fetches a single project status detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchProjectStatus(id: number): Promise<ProjectStatusDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<ProjectStatusDetail, ResourcePermissions>
  >(`/project-statuses/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a project status. Returns the created resource from the envelope `data`. */
export async function createProjectStatus(
  payload: CreateProjectStatusPayload,
): Promise<ProjectStatusDetail> {
  const { data } = await apiClient.post<ApiResponse<ProjectStatusDetail>>(
    '/project-statuses',
    payload,
  )
  return data.data
}

/** Partially updates a project status (PATCH). Returns the updated resource. */
export async function updateProjectStatus(
  id: number,
  payload: UpdateProjectStatusPayload,
): Promise<ProjectStatusDetail> {
  const { data } = await apiClient.patch<ApiResponse<ProjectStatusDetail>>(
    `/project-statuses/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a project status. Backend responds 204 with no body, or 409 when
 * the status is still referenced by a Project or a Campaign (BR-4) — the
 * caller surfaces the backend's `message` for that case.
 */
export async function deleteProjectStatus(id: number): Promise<void> {
  await apiClient.delete(`/project-statuses/${id}`)
}
