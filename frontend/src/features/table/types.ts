/**
 * Types for the generic, domain-driven DataTable framework.
 * Source of truth: docs/api/0002-generic-tables.md (frozen API contract).
 *
 * One pair of endpoints serves every domain; the `{domain}` segment selects the
 * server-side definition. These types are domain-agnostic: the frontend renders
 * whatever schema the backend returns for a given domain.
 */

/** Field type that drives the default cell rendering/formatting. */
export type ColumnType = 'text' | 'number' | 'datetime' | 'enum' | 'tags' | 'badge' | 'boolean'

/**
 * Per-value badge metadata for a `badge` column, supplied by the backend from a
 * domain enum (EnumMeta). The frontend maps a row value to its entry to render
 * the label/color/icon — it never knows the domain enum itself.
 */
export interface EnumBadge {
  value: string
  label: string
  /** Color token (e.g. "blue", "violet"); mapped to a badge style on render. */
  color: string | null
  /** Optional icon name. */
  icon: string | null
  is_default?: boolean
  hidden_on_form?: boolean
}

/** AG Grid filter type advertised per column in the config catalog. */
export type FilterType = 'text' | 'number' | 'date' | 'set' | 'boolean'

/** How a row action should be rendered. */
export type ActionType = 'link' | 'action' | 'danger'

/**
 * A single primary contact as carried by a row's `primary_contact` cell value
 * (the Users domain). Structured so the frontend renders the type icon + label
 * without knowing the contact-type domain. `icon` is a backend icon token.
 */
export interface PrimaryContact {
  type: string
  icon: string | null
  label: string
  value: string
}

/** A single column declared by the backend config endpoint. */
export interface TableColumn {
  /** Stable key = real DB column name (or derived field like "roles"). */
  id: string
  /** i18n key, e.g. "users.columns.name". */
  label: string
  type: ColumnType
  /**
   * AG Grid filter type advertised per column in the config catalog. When
   * present it drives the filter component; otherwise it falls back to `type`.
   */
  filterType?: FilterType
  /** Visibility (default, or the user's saved override). */
  visible: boolean
  /**
   * Column width in px, or null when there is no explicit default (the grid
   * applies its own). User-overridable and persisted. See 0003.
   */
  width: number | null
  /**
   * Stable ordering key; columns arrive already sorted by it. User-overridable
   * and persisted. See 0003.
   */
  order: number
  /** Server-side whitelist: sort accepted only when true. */
  sortable: boolean
  /** Server-side whitelist: filter accepted only when true. */
  filterable: boolean
  /**
   * Whether the column supports a Set Filter value list (POST /values). `false`
   * for computed/derived columns without a queryable value list (e.g. a
   * concatenated address or a nested contact) — those get a conditions-only
   * filter, never a Set tab. Absent or `true` behaves as supported (0005).
   */
  hasFilterValues?: boolean
  /** Allowed values for enum/tags/badge columns (may be resolved dynamically). */
  options?: string[]
  /**
   * Per-value badge metadata for a `badge` column. Present only when the backend
   * declares the column as a badge; drives label/color/icon rendering.
   */
  badges?: EnumBadge[]
  /**
   * The snake_case domain-enum key this column maps to (e.g. `personal_data_type`
   * for the `user_type` badge column). When present, the frontend localizes the
   * badge label from its i18n resources (`enums.<enumKey>.<value>`) instead of the
   * backend-supplied `badges[].label`.
   */
  enumKey?: string
}

/**
 * One column's state sent to POST /tables/{domain}/preferences. The backend
 * diffs this against the default and stores only the deviations (0003). `width`
 * is omitted (not null) when the column has no explicit width, so it satisfies
 * the integer validation.
 */
export interface ColumnPreferenceInput {
  id: string
  visible: boolean
  width?: number
  order: number
}

/** A filter entry from the config filter catalog. */
export interface TableFilter {
  columnId: string
  type: FilterType
  /** Static or dynamically-resolved option list for set filters. */
  options?: string[]
}

/** Action catalog entry describing how to render a given action key. */
export interface TableActionDefinition {
  key: string
  /** i18n key, e.g. "actions.view". */
  label: string
  /** Icon name mapped to a Lucide component. */
  icon: string
  type: ActionType
  /** Whether the action requires UI confirmation before firing. */
  confirm: boolean
}

/** A single sort directive. */
export interface TableSort {
  columnId: string
  direction: 'asc' | 'desc'
}

/** Default pagination state for the grid. */
export interface TablePagination {
  limit: number
}

/**
 * Full table schema returned by GET /tables/{domain}/columns (envelope `data`).
 * `resource` echoes the `{domain}` key.
 */
export interface TableConfig {
  resource: string
  columns: TableColumn[]
  filters: TableFilter[]
  actions: TableActionDefinition[]
  defaultSort: TableSort[]
  defaultPagination: TablePagination
  /**
   * Whether the current user has a saved layout for this table (so the UI can
   * offer "reset to default" only when there is something to reset). See 0003.
   */
  customized: boolean
}

/**
 * A row returned by POST /tables/{domain}/rows. Fields are schema-driven, so
 * values are loosely typed; `actions` is the per-row whitelist of allowed action
 * keys (computed server-side via the domain Policy).
 */
export interface TableRow {
  id: number
  /** Allowed action keys for THIS row. */
  actions: string[]
  [key: string]: unknown
}

/** Sort item as serialized in the SSRM request body. */
export interface SsrmSortModelItem {
  colId: string
  sort: 'asc' | 'desc'
}

/** SSRM rows request payload (AG Grid IServerSideGetRowsRequest subset). */
export interface TableRowsPayload {
  startRow: number
  endRow: number
  sortModel: SsrmSortModelItem[]
  filterModel: Record<string, unknown>
}

/** Pagination metadata from the `paginatedResponse()` envelope. */
export interface RowsPaginationMeta {
  total: number
  offset: number
  limit: number
  total_pages: number
}

/** Response of POST /tables/{domain}/rows (`paginatedResponse()` envelope). */
export interface TableRowsResponse {
  items: TableRow[]
  export_link: string | null
  pagination: RowsPaginationMeta
}

/**
 * POST /tables/{domain}/values request payload: distinct values for one
 * column's Set Filter, server-side and Excel-like (0004). `filterModel`
 * carries the filters currently active on the OTHER columns; the backend
 * ignores an entry for `columnId` itself.
 */
export interface TableColumnValuesPayload {
  columnId: string
  search?: string
  limit?: number
  filterModel?: Record<string, unknown>
}

/** Response of POST /tables/{domain}/values (envelope `data`). */
export interface TableColumnValuesResponse {
  values: string[]
  hasMore: boolean
}
