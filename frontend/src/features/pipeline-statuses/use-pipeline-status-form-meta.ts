import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { PipelineStatusFormMode } from '@/features/pipeline-statuses/types'

/** Metadata-loading state driving what `PipelineStatusForm` renders. */
export type PipelineStatusFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.pipelineStatus.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/pipeline-statuses`)
 * once.
 */
export function usePipelineStatusFormMeta(mode: PipelineStatusFormMode): PipelineStatusFormMetaState {
  const metaQuery = useResourceMeta('pipeline-statuses', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.pipelineStatus.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
