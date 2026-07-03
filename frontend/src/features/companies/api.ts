import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CompanyDetail,
  CompanyDetailWithPermissions,
  CreateCompanyPayload,
  UpdateCompanyPayload,
} from '@/features/companies/types'

/**
 * Fetches a single company detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchCompany(id: number): Promise<CompanyDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<CompanyDetail, ResourcePermissions>
  >(`/companies/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a company. Returns the created resource from the envelope `data`. */
export async function createCompany(
  payload: CreateCompanyPayload,
): Promise<CompanyDetail> {
  const { data } = await apiClient.post<ApiResponse<CompanyDetail>>(
    '/companies',
    payload,
  )
  return data.data
}

/** Partially updates a company (PATCH). Returns the updated resource. */
export async function updateCompany(
  id: number,
  payload: UpdateCompanyPayload,
): Promise<CompanyDetail> {
  const { data } = await apiClient.patch<ApiResponse<CompanyDetail>>(
    `/companies/${id}`,
    payload,
  )
  return data.data
}

/** Deletes a company. Backend responds 204 with no body. */
export async function deleteCompany(id: number): Promise<void> {
  await apiClient.delete(`/companies/${id}`)
}
