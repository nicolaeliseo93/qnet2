import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { OpportunityWorkflowFormMode } from '@/features/opportunity-workflows/types'

/** Metadata-loading state driving what `OpportunityWorkflowForm` renders. */
export type OpportunityWorkflowFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.opportunityWorkflow.permissions`, fetched by the `show` endpoint);
 * create mode fetches the create-context metadata (`GET
 * /meta/opportunity-workflows`) once. Only `name`/`is_active` are covered by
 * the field-permission catalogue (`OpportunityWorkflowsAuthorization`); the
 * nested `criteria`/`statuses` collections are edited via their own request
 * payload keys and are not field-permission-gated.
 */
export function useOpportunityWorkflowFormMeta(
  mode: OpportunityWorkflowFormMode,
): OpportunityWorkflowFormMetaState {
  const metaQuery = useResourceMeta('opportunity-workflows', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.opportunityWorkflow.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
