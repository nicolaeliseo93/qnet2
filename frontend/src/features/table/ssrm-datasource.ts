import type {
  IServerSideDatasource,
  IServerSideGetRowsParams,
} from 'ag-grid-community'
import { fetchTableRows } from '@/features/table/api'
import type { SsrmSortModelItem, TableRow } from '@/features/table/types'

/** Page size fallback when the grid does not provide an explicit block range. */
const DEFAULT_BLOCK_SIZE = 25

/**
 * Builds an AG Grid Server-Side Row Model datasource for a given `domain`.
 *
 * The datasource is a thin adapter: it forwards the SSRM request (startRow,
 * endRow, sortModel, filterModel) to `POST /tables/{domain}/rows` via the API
 * layer, maps the `paginatedResponse()` envelope (`items` + `pagination.total`)
 * to `params.success({ rowData, rowCount })`, and signals failures with
 * `params.fail()` so the grid can recover its state.
 *
 * The global quick-search term (spec 0009) is NOT part of the AG Grid SSRM
 * request, so the caller supplies it through the `getSearch` getter, read at
 * request time. The datasource instance stays stable across renders; the caller
 * calls `refreshServerSide({ purge: true })` when the debounced term changes.
 *
 * Domain-agnostic: the only domain-specific input is the `domain` key. The same
 * datasource powers every table.
 */
export function createSsrmDatasource(
  domain: string,
  getSearch?: () => string,
): IServerSideDatasource<TableRow> {
  return {
    async getRows(params: IServerSideGetRowsParams<TableRow>): Promise<void> {
      const { request } = params
      const startRow = request.startRow ?? 0
      const endRow = request.endRow ?? startRow + DEFAULT_BLOCK_SIZE

      const sortModel: SsrmSortModelItem[] = request.sortModel.map((item) => ({
        colId: item.colId,
        sort: item.sort,
      }))

      // filterModel can be a plain map, an advanced model, or null — normalize to
      // the simple object the backend contract validates against.
      const filterModel: Record<string, unknown> =
        request.filterModel && !Array.isArray(request.filterModel)
          ? (request.filterModel as Record<string, unknown>)
          : {}

      // Only send `search` when there is a non-empty term (keeps the request
      // clean and lets the backend skip the OR-LIKE entirely otherwise).
      const search = getSearch?.().trim() ?? ''

      try {
        const response = await fetchTableRows(domain, {
          startRow,
          endRow,
          sortModel,
          filterModel,
          ...(search !== '' ? { search } : {}),
        })

        params.success({
          rowData: response.items,
          rowCount: response.pagination.total,
        })
      } catch {
        params.fail()
      }
    },
  }
}
