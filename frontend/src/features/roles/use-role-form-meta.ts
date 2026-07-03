import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { RoleFormMode } from '@/features/roles/role-form'

/** Metadata-loading state driving what `RoleForm` renders. */
export type RoleFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.role.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/roles`) once.
 */
export function useRoleFormMeta(mode: RoleFormMode): RoleFormMetaState {
  const metaQuery = useResourceMeta('roles', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.role.authorization }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
