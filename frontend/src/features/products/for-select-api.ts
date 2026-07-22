import { fetchForSelect } from '@/features/for-select/api'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the products for-select endpoint. */
export const PRODUCTS_FOR_SELECT_RESOURCE = 'products'

/**
 * Fetches a page of product options from `GET /api/products/for-select`.
 * Thin wrapper over the generic for-select fetcher, bound to the `products`
 * resource. Items carry `label` (name) and `subtitle` (their category); pass
 * `params: { category_ids: [...] }` to scope the page to those categories —
 * how the "prodotti di interesse" picker stays aligned with the
 * opportunity's product lines.
 */
export function fetchProductsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(PRODUCTS_FOR_SELECT_RESOURCE, params)
}
