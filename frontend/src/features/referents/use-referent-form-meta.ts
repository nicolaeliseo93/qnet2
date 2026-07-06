import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { ReferentFormMode } from '@/features/referents/types'

/** Metadata-loading state driving what `ReferentForm` renders. */
export type ReferentFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.referent.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/referents`) once.
 */
export function useReferentFormMeta(mode: ReferentFormMode): ReferentFormMetaState {
  const metaQuery = useResourceMeta('referents', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.referent.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
