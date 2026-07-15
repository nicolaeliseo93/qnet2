import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { getImportRunHistory } from '@/features/imports/wizard/api'
import { importWizardKeys } from '@/features/imports/wizard/query-keys'
import type { ImportRunHistoryPage } from '@/features/imports/wizard/types'

/** Page size of the import history list (spec 0033 AC-018, F5 lane). */
const HISTORY_PAGE_SIZE = 10

/**
 * Loads the actor's own paginated runs for a domain (`GET /imports/{domain}`,
 * spec 0033 AC-018). Server state stays in TanStack Query; the current page
 * is plain client UI state, owned locally rather than persisted anywhere.
 */
export function useImportHistory(domain: string) {
  const [page, setPage] = useState(1)

  const query = useQuery<ImportRunHistoryPage>({
    queryKey: [...importWizardKeys.domain(domain), 'history', page, HISTORY_PAGE_SIZE],
    queryFn: () => getImportRunHistory(domain, page, HISTORY_PAGE_SIZE),
  })

  return {
    items: query.data?.items ?? [],
    pagination: query.data?.pagination ?? null,
    isLoading: query.isLoading,
    isError: query.isError,
    refetch: query.refetch,
    page,
    setPage,
  }
}
