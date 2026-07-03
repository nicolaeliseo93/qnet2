import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { UserFormMode } from '@/features/users/user-form'

/** Metadata-loading state driving what `UserForm` renders. */
export type UserFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.user.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/users`) once.
 */
export function useUserFormMeta(mode: UserFormMode): UserFormMetaState {
  const metaQuery = useResourceMeta('users', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.user.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
