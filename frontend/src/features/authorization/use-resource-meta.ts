import { useQuery } from '@tanstack/react-query'
import { fetchResourceMeta } from '@/features/authorization/api'
import { metaKeys } from '@/features/authorization/query-keys'

/**
 * Authorization rarely changes within a session, so the create-context
 * metadata is cached for 5 minutes rather than refetched on every mount.
 */
const META_STALE_TIME_MS = 5 * 60 * 1000

/**
 * Loads the create-context metadata (field catalogue + permissions) for a
 * resource. `enabled` lets a caller defer the fetch when the create-context
 * is not needed (e.g. a form currently in edit mode, which seeds permissions
 * from the loaded instance detail instead).
 */
export function useResourceMeta(resource: string, enabled = true) {
  return useQuery({
    queryKey: metaKeys.resource(resource),
    queryFn: () => fetchResourceMeta(resource),
    staleTime: META_STALE_TIME_MS,
    enabled,
  })
}
