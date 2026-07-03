import { apiClient } from '@/api/client'
import type { ApiResponseWithPermissions } from '@/api/types'
import type {
  FieldDescriptor,
  ResourceMeta,
  ResourcePermissions,
} from '@/features/authorization/types'

interface MetaData {
  fields: FieldDescriptor[]
}

/**
 * Fetches the create-context metadata for a resource: the static field
 * catalogue plus the actor's create-context `permissions` block. Unwraps the
 * `{ data: { fields }, permissions }` envelope into a flat `ResourceMeta`.
 */
export async function fetchResourceMeta(resource: string): Promise<ResourceMeta> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<MetaData, ResourcePermissions>
  >(`/meta/${resource}`)
  return { fields: data.data.fields, permissions: data.permissions }
}
