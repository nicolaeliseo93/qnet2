import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { SourceFormMode } from '@/features/sources/types'

/** Metadata-loading state driving what `SourceForm` renders. */
export type SourceFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.source.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/sources`) once.
 */
export function useSourceFormMeta(mode: SourceFormMode): SourceFormMetaState {
  const metaQuery = useResourceMeta('sources', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.source.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
