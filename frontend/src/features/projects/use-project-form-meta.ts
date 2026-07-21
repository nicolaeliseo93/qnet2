import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { ProjectFormMode } from '@/features/projects/types'

/** Metadata-loading state driving what `ProjectForm` renders. */
export type ProjectFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.project.permissions`,
 * fetched by the `show` endpoint); create AND duplicate mode (row action
 * "duplicate" still submits via the create path) fetch the create-context
 * metadata (`GET /meta/projects`) once.
 */
export function useProjectFormMeta(mode: ProjectFormMode): ProjectFormMetaState {
  const metaQuery = useResourceMeta('projects', mode.type !== 'edit')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.project.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
