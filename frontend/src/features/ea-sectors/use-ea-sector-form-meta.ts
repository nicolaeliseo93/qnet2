import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { EaSectorFormMode } from '@/features/ea-sectors/types'

/** Metadata-loading state driving what `EaSectorForm` renders. */
export type EaSectorFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.sector.permissions`, fetched by the `show` endpoint); create mode
 * fetches the create-context metadata (`GET /meta/ea-sectors`) once.
 */
export function useEaSectorFormMeta(mode: EaSectorFormMode): EaSectorFormMetaState {
  const metaQuery = useResourceMeta('ea-sectors', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.sector.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
