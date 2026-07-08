import { useQuery } from '@tanstack/react-query'
import { fetchSectorTree } from '@/features/sectors/api'
import { sectorKeys } from '@/features/sectors/query-keys'

/**
 * Loads the full sector tree. Consumed by the create/edit form's parent
 * picker (flattened — see `flattenSectorTree`).
 */
export function useSectorTree() {
  return useQuery({
    queryKey: sectorKeys.tree,
    queryFn: fetchSectorTree,
  })
}
