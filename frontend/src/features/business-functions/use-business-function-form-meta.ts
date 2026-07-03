import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { BusinessFunctionFormMode } from '@/features/business-functions/types'

/** Metadata-loading state driving what `BusinessFunctionForm` renders. */
export type BusinessFunctionFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.businessFunction.permissions`, fetched by the `show` endpoint);
 * create mode fetches the create-context metadata
 * (`GET /meta/business-functions`) once.
 */
export function useBusinessFunctionFormMeta(
  mode: BusinessFunctionFormMode,
): BusinessFunctionFormMetaState {
  const metaQuery = useResourceMeta('business-functions', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.businessFunction.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
