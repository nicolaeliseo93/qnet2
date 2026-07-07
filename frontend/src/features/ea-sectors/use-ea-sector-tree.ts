import { useQuery } from '@tanstack/react-query'
import { fetchEaSectorTree } from '@/features/ea-sectors/api'
import { eaSectorKeys } from '@/features/ea-sectors/query-keys'

/**
 * Loads the full sector tree. Consumed by the create/edit form's parent
 * picker (flattened — see `flattenEaSectorTree`).
 */
export function useEaSectorTree() {
  return useQuery({
    queryKey: eaSectorKeys.tree,
    queryFn: fetchEaSectorTree,
  })
}
