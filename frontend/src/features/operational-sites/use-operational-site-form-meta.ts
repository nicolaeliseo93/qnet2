import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { OperationalSiteFormMode } from '@/features/operational-sites/types'

/** Metadata-loading state driving what `OperationalSiteForm` renders. */
export type OperationalSiteFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.operationalSite.permissions`, fetched by the `show` endpoint);
 * create mode fetches the create-context metadata
 * (`GET /meta/operational-sites`) once.
 */
export function useOperationalSiteFormMeta(
  mode: OperationalSiteFormMode,
): OperationalSiteFormMetaState {
  const metaQuery = useResourceMeta('operational-sites', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.operationalSite.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
