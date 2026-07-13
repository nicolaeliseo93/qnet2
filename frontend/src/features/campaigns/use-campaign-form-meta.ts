import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CampaignFormMode } from '@/features/campaigns/types'

/** Metadata-loading state driving what `CampaignForm` renders. */
export type CampaignFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.campaign.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/campaigns`) once.
 */
export function useCampaignFormMeta(mode: CampaignFormMode): CampaignFormMetaState {
  const metaQuery = useResourceMeta('campaigns', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.campaign.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
