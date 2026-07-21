import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateOpportunityWorkflowPayload,
  CriterionFieldOption,
  OpportunityWorkflowDetail,
  OpportunityWorkflowDetailWithPermissions,
  OpportunityWorkflowStatusItem,
  UpdateDefaultStatusesPayload,
  UpdateOpportunityWorkflowPayload,
} from '@/features/opportunity-workflows/types'

/**
 * Fetches a single opportunity workflow detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchOpportunityWorkflow(id: number): Promise<OpportunityWorkflowDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<OpportunityWorkflowDetail, ResourcePermissions>
  >(`/opportunity-workflows/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates an opportunity workflow (criteria + optional custom statuses in one request). */
export async function createOpportunityWorkflow(
  payload: CreateOpportunityWorkflowPayload,
): Promise<OpportunityWorkflowDetail> {
  const { data } = await apiClient.post<ApiResponse<OpportunityWorkflowDetail>>(
    '/opportunity-workflows',
    payload,
  )
  return data.data
}

/** Updates an opportunity workflow. `criteria`/`statuses`, when present, are authoritative syncs. */
export async function updateOpportunityWorkflow(
  id: number,
  payload: UpdateOpportunityWorkflowPayload,
): Promise<OpportunityWorkflowDetail> {
  const { data } = await apiClient.patch<ApiResponse<OpportunityWorkflowDetail>>(
    `/opportunity-workflows/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes an opportunity workflow. The backend re-resolves every impacted
 * Opportunity in the same transaction (AC-018) — no cleanup needed here.
 */
export async function deleteOpportunityWorkflow(id: number): Promise<void> {
  await apiClient.delete(`/opportunity-workflows/${id}`)
}

/** Fetches the allow-listed criterion fields (AC-022), for the criteria editor's field select. */
export async function fetchCriterionFields(): Promise<CriterionFieldOption[]> {
  const { data } = await apiClient.get<ApiResponse<CriterionFieldOption[]>>(
    '/opportunity-workflows/criterion-fields',
  )
  return data.data
}

/** Fetches the GLOBAL default status set, ordered by `sort_order`. */
export async function fetchDefaultStatuses(): Promise<OpportunityWorkflowStatusItem[]> {
  const { data } = await apiClient.get<ApiResponse<OpportunityWorkflowStatusItem[]>>(
    '/opportunity-workflows/default-statuses',
  )
  return data.data
}

/** Syncs the GLOBAL default status set's custom rows + order. Returns the fresh, full ordered list. */
export async function updateDefaultStatuses(
  payload: UpdateDefaultStatusesPayload,
): Promise<OpportunityWorkflowStatusItem[]> {
  const { data } = await apiClient.put<ApiResponse<OpportunityWorkflowStatusItem[]>>(
    '/opportunity-workflows/default-statuses',
    payload,
  )
  return data.data
}
