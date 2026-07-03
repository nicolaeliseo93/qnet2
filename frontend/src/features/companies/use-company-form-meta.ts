import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CompanyFormMode } from '@/features/companies/company-form'

/** Metadata-loading state driving what `CompanyForm` renders. */
export type CompanyFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.company.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/companies`) once.
 */
export function useCompanyFormMeta(mode: CompanyFormMode): CompanyFormMetaState {
  const metaQuery = useResourceMeta('companies', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.company.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
