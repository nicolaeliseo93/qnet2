import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CompanySiteDetail,
  CompanySiteDetailWithPermissions,
  CreateCompanySitePayload,
  UpdateCompanySitePayload,
} from '@/features/company-sites/types'

/**
 * Fetches a single company-site detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchCompanySite(id: number): Promise<CompanySiteDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<CompanySiteDetail, ResourcePermissions>
  >(`/company-sites/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a company site. Returns the created resource from the envelope `data`. */
export async function createCompanySite(
  payload: CreateCompanySitePayload,
): Promise<CompanySiteDetail> {
  const { data } = await apiClient.post<ApiResponse<CompanySiteDetail>>(
    '/company-sites',
    payload,
  )
  return data.data
}

/** Partially updates a company site (PATCH). Returns the updated resource. */
export async function updateCompanySite(
  id: number,
  payload: UpdateCompanySitePayload,
): Promise<CompanySiteDetail> {
  const { data } = await apiClient.patch<ApiResponse<CompanySiteDetail>>(
    `/company-sites/${id}`,
    payload,
  )
  return data.data
}

/** Deletes a company site. Backend responds 204 with no body. */
export async function deleteCompanySite(id: number): Promise<void> {
  await apiClient.delete(`/company-sites/${id}`)
}

/**
 * Sets this site as the company's default one. Server-side, in a single
 * transaction, every other site's `is_default` is cleared (spec 0020: at
 * most one default site at a time).
 */
export async function setDefaultCompanySite(id: number): Promise<CompanySiteDetail> {
  const { data } = await apiClient.post<ApiResponse<CompanySiteDetail>>(
    `/company-sites/${id}/set-default`,
  )
  return data.data
}

/** Uploads (replacing any previous) logo for an existing company site. */
export async function uploadCompanySiteLogo(
  id: number,
  file: File,
): Promise<CompanySiteDetail> {
  const formData = new FormData()
  formData.append('logo', file)
  const { data } = await apiClient.post<ApiResponse<CompanySiteDetail>>(
    `/company-sites/${id}/logo`,
    formData,
  )
  return data.data
}

/** Removes the current logo of an existing company site. */
export async function deleteCompanySiteLogo(id: number): Promise<CompanySiteDetail> {
  const { data } = await apiClient.delete<ApiResponse<CompanySiteDetail>>(
    `/company-sites/${id}/logo`,
  )
  return data.data
}
