import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateCustomFieldDefinitionPayload,
  CustomFieldDefinitionDetail,
  CustomFieldDefinitionDetailWithPermissions,
  UpdateCustomFieldDefinitionPayload,
} from '@/features/custom-fields/types'

/** One custom-fieldable module, as listed by `GET /custom-fields/entities` (admin entity_type picker). */
export interface CustomFieldEntity {
  entity_type: string
  label: string
}

/** Populates the admin form's `entity_type` (and `relation_target`) pickers. */
export async function fetchCustomFieldEntities(): Promise<CustomFieldEntity[]> {
  const { data } = await apiClient.get<ApiResponse<CustomFieldEntity[]>>('/custom-fields/entities')
  return data.data
}

/*
 * ---------------------------------------------------------------------------
 * ADMIN CRUD (spec 0021 — ADMIN PANEL): `CustomFieldDefinition` catalogue.
 * ---------------------------------------------------------------------------
 */

/**
 * Fetches a single custom field definition together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchCustomFieldDefinition(
  id: number,
): Promise<CustomFieldDefinitionDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<CustomFieldDefinitionDetail, ResourcePermissions>
  >(`/custom-fields/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a custom field definition. Returns the created resource from the envelope `data`. */
export async function createCustomFieldDefinition(
  payload: CreateCustomFieldDefinitionPayload,
): Promise<CustomFieldDefinitionDetail> {
  const { data } = await apiClient.post<ApiResponse<CustomFieldDefinitionDetail>>(
    '/custom-fields',
    payload,
  )
  return data.data
}

/** Partially updates a custom field definition (PATCH). Returns the updated resource. */
export async function updateCustomFieldDefinition(
  id: number,
  payload: UpdateCustomFieldDefinitionPayload,
): Promise<CustomFieldDefinitionDetail> {
  const { data } = await apiClient.patch<ApiResponse<CustomFieldDefinitionDetail>>(
    `/custom-fields/${id}`,
    payload,
  )
  return data.data
}

/** Deletes a custom field definition. Backend responds 204 with no body. */
export async function deleteCustomFieldDefinition(id: number): Promise<void> {
  await apiClient.delete(`/custom-fields/${id}`)
}
