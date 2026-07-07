import { useQuery } from '@tanstack/react-query'
import { fetchProductCategoryTree } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'

/**
 * Loads the full category tree. Shared by the tree view (navigation/CRUD) and
 * the product form's category picker (flattened — see `flattenCategoryTree`),
 * so both stay in sync from a single cached fetch.
 */
export function useProductCategoryTree() {
  return useQuery({
    queryKey: productCategoryKeys.tree,
    queryFn: fetchProductCategoryTree,
  })
}
