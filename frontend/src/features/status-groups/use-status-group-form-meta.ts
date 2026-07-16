import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { StatusGroupFormMode } from '@/features/status-groups/types'

/** Metadata-loading state driving what `StatusGroupForm` renders. */
export type StatusGroupFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.statusGroup.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/status-groups`) once.
 */
export function useStatusGroupFormMeta(mode: StatusGroupFormMode): StatusGroupFormMetaState {
  const metaQuery = useResourceMeta('status-groups', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.statusGroup.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
