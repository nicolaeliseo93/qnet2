import { useQuery } from '@tanstack/react-query'
import { fetchTableConfig } from '@/features/table/api'

/** Query keys for the generic table feature, namespaced by domain. */
export const tableKeys = {
  config: (domain: string) => ['table', domain, 'config'] as const,
}

/**
 * Loads a domain's table schema. The config is semi-static (changes only with
 * permissions/schema), so it is cached aggressively to avoid refetching on every
 * mount while the grid streams rows separately via the SSRM datasource. The
 * query key includes the domain so each table caches independently.
 */
export function useTableConfig(domain: string) {
  return useQuery({
    queryKey: tableKeys.config(domain),
    queryFn: () => fetchTableConfig(domain),
    staleTime: 10 * 60 * 1000,
  })
}
