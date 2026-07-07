import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CompanySiteFormMode } from '@/features/company-sites/company-site-form'

/** Metadata-loading state driving what `CompanySiteForm` renders. */
export type CompanySiteFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.companySite.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/company-sites`) once.
 */
export function useCompanySiteFormMeta(mode: CompanySiteFormMode): CompanySiteFormMetaState {
  const metaQuery = useResourceMeta('company-sites', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.companySite.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
