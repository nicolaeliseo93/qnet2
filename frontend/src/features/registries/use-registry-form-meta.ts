import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { RegistryFormMode } from '@/features/registries/types'

/** Metadata-loading state driving what `RegistryForm` renders. */
export type RegistryFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.registry.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/registries`) once.
 */
export function useRegistryFormMeta(mode: RegistryFormMode): RegistryFormMetaState {
  const metaQuery = useResourceMeta('registries', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.registry.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
