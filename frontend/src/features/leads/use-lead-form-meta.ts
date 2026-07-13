import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { LeadFormMode } from '@/features/leads/types'

/** Metadata-loading state driving what `LeadForm` renders. */
export type LeadFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.lead.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/leads`) once.
 */
export function useLeadFormMeta(mode: LeadFormMode): LeadFormMetaState {
  const metaQuery = useResourceMeta('leads', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.lead.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
