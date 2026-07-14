import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { PaginatedResponse } from '@/features/notifications/types'
import type {
  CreateProjectPayload,
  ProjectCard,
  ProjectCardListParams,
  ProjectDetail,
  ProjectDetailWithPermissions,
  UpdateProjectPayload,
} from '@/features/projects/types'

/** Default page size requested per infinite-scroll fetch of the card grid (spec 0026). */
export const PROJECT_CARDS_PAGE_SIZE = 12

/**
 * Query key of a single project's detail (fresh-on-open pattern). Shared by
 * the detail/edit pages and by the post-mutation invalidation, so they can
 * never drift apart. `null` (an unparsable route param) is a key that is never
 * fetched.
 */
export function projectDetailQueryKey(id: number | null) {
  return ['projects', 'detail', id] as const
}

/**
 * Prefix shared by every filtered variant of the card grid's query. Matches
 * them all at once for a broad invalidation (e.g. after a create from the
 * unified page header, regardless of which view is currently mounted).
 */
export function projectCardsQueryKeyPrefix() {
  return ['projects', 'cards'] as const
}

/** Query key of the card grid's infinite list, scoped by its request filters. */
export function projectCardsQueryKey(filters: Pick<ProjectCardListParams, 'search' | 'project_status_id'>) {
  return [...projectCardsQueryKeyPrefix(), filters] as const
}

/** Fetches a page of project cards for the card grid (spec 0026 AC-001/002). */
export async function fetchProjectCards(
  params: ProjectCardListParams = {},
): Promise<PaginatedResponse<ProjectCard>> {
  const { search, offset = 0, limit = PROJECT_CARDS_PAGE_SIZE, project_status_id } = params
  const { data } = await apiClient.get<PaginatedResponse<ProjectCard>>('/projects', {
    params: {
      offset,
      limit,
      ...(search ? { search } : {}),
      ...(project_status_id ? { project_status_id } : {}),
    },
  })
  return data
}

/**
 * Fetches a single project detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchProject(id: number): Promise<ProjectDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<ProjectDetail, ResourcePermissions>
  >(`/projects/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a project. `code` is server-generated (BR-1); returns the created resource. */
export async function createProject(payload: CreateProjectPayload): Promise<ProjectDetail> {
  const { data } = await apiClient.post<ApiResponse<ProjectDetail>>('/projects', payload)
  return data.data
}

/** Partially updates a project (PATCH). Returns the updated resource. */
export async function updateProject(
  id: number,
  payload: UpdateProjectPayload,
): Promise<ProjectDetail> {
  const { data } = await apiClient.patch<ApiResponse<ProjectDetail>>(`/projects/${id}`, payload)
  return data.data
}

/** Deletes a project. Backend responds 204 with no body (409 if it still has campaigns, BR-5). */
export async function deleteProject(id: number): Promise<void> {
  await apiClient.delete(`/projects/${id}`)
}
