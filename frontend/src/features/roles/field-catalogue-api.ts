import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { FieldDescriptor } from '@/features/authorization/types'

/** One registered authorization resource's static field catalogue. */
export interface FieldCatalogueResource {
  resource: string
  fields: FieldDescriptor[]
}

/** Response shape of `GET /authorization/fields`, already unwrapped from the envelope. */
export interface FieldCatalogue {
  resources: FieldCatalogueResource[]
}

/**
 * Fetches the field catalogue for every resource registered in
 * `config/authorization.php` (spec 0006) — drives the Role form's
 * field-permission matrix. No `permissions` envelope sibling here (unlike
 * `GET /meta/{resource}`): this endpoint is authorized once, up front
 * (`roles.create` OR `roles.update`), not per-resource.
 */
export async function fetchFieldCatalogue(): Promise<FieldCatalogue> {
  const { data } = await apiClient.get<ApiResponse<FieldCatalogue>>(
    '/authorization/fields',
  )
  return data.data
}
