import { useQuery } from '@tanstack/react-query'
import { fetchSystemStatusId } from '@/features/status-reorder/api'

/** How long the resolved id is considered fresh: system rows are effectively static reference data. */
const STALE_TIME_MS = 5 * 60 * 1000

/**
 * Resolves the id of a resource's system status (spec 0039 D-3), used to
 * preselect "Nuovo" on create when the field is left untouched by the user.
 * Shared by the leads/projects/campaigns create forms — both `pipeline-statuses`
 * and `lead-statuses` expose the same `meta.system_key` for-select marker.
 */
export function useDefaultSystemStatusId(resource: string, key: 'new' | 'closed', enabled: boolean) {
  return useQuery({
    queryKey: ['status-reorder', resource, 'system-status', key],
    queryFn: () => fetchSystemStatusId(resource, key),
    enabled,
    staleTime: STALE_TIME_MS,
  })
}
