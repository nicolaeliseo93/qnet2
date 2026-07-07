import { useQuery } from '@tanstack/react-query'
import { fetchEffectiveAttributes } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'

/**
 * Loads a category's effective attributes (own + inherited), gated on a
 * selected category id. Used by the product form to generate its dynamic
 * attribute fields (spec AC-023): the query key includes the category id, so
 * switching category re-fetches and the caller regenerates the fields.
 */
export function useEffectiveAttributes(categoryId: number | null) {
  return useQuery({
    queryKey: productCategoryKeys.effectiveAttributes(categoryId ?? 0),
    queryFn: () => fetchEffectiveAttributes(categoryId as number),
    enabled: categoryId !== null,
  })
}
