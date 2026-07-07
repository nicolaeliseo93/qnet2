import { useQuery } from '@tanstack/react-query'
import { fetchTableRows } from '@/features/table/api'
import type { AttributeDataType } from '@/features/attributes/types'

/** A minimal attribute projection for pickers (assignment editors, dynamic forms). */
export interface AttributeCatalogEntry {
  id: number
  code: string
  name: string
  data_type: AttributeDataType
}

/**
 * Upper bound of attributes fetched for a picker; the catalog is expected to stay small
 * (global, reusable). Capped at the generic rows endpoint's max block size (MAX_LIMIT = 100):
 * a single `/tables/attributes/rows` request rejects a block larger than 100 (422), so a
 * higher value here silently empties the picker. If the catalog ever outgrows 100, switch to
 * a search-driven/paginated source instead of raising this.
 */
const ATTRIBUTE_CATALOG_LIMIT = 100

/**
 * Loads the full attribute catalog for assignment pickers (e.g. the
 * product-category attribute-assignment editor). Reuses the already-frozen
 * generic table rows endpoint (`POST /tables/attributes/rows`) instead of a
 * new `for-select` endpoint — out of scope for spec 0017.
 */
export function useAttributeCatalog() {
  return useQuery({
    queryKey: ['attributes', 'catalog'],
    queryFn: async (): Promise<AttributeCatalogEntry[]> => {
      const response = await fetchTableRows('attributes', {
        startRow: 0,
        endRow: ATTRIBUTE_CATALOG_LIMIT,
        sortModel: [{ colId: 'name', sort: 'asc' }],
        filterModel: {},
      })
      return response.items.map((item) => ({
        id: item.id,
        code: String(item.code),
        name: String(item.name),
        data_type: item.data_type as AttributeDataType,
      }))
    },
  })
}
