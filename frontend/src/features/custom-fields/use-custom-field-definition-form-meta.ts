import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDefinitionFormMode } from '@/features/custom-fields/types'

/** Metadata-loading state driving what `CustomFieldDefinitionFormBody` renders. */
export type CustomFieldDefinitionFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.definition.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/custom-fields`) once
 * (mirrors `useAttributeFormMeta`).
 */
export function useCustomFieldDefinitionFormMeta(
  mode: CustomFieldDefinitionFormMode,
): CustomFieldDefinitionFormMetaState {
  const metaQuery = useResourceMeta('custom-fields', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.definition.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
