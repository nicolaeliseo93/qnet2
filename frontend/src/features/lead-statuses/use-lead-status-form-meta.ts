import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { LeadStatusFormMode } from '@/features/lead-statuses/types'

/** Metadata-loading state driving what `LeadStatusForm` renders. */
export type LeadStatusFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.leadStatus.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/lead-statuses`) once.
 */
export function useLeadStatusFormMeta(mode: LeadStatusFormMode): LeadStatusFormMetaState {
  const metaQuery = useResourceMeta('lead-statuses', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.leadStatus.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
