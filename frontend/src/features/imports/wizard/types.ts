/**
 * Advanced lead import wizard types (spec 0033). Every shape below mirrors
 * the frozen backend `data_contract` 1:1 — no field is invented here. The
 * legacy two-phase import types (`features/imports/types.ts`, spec 0012)
 * are untouched; this module is a parallel, wizard-only contract.
 */

/** Lifecycle of a wizard import run, mirrored from the extended `App\Enums\ImportStatus`. */
export type ImportRunStatus =
  | 'analyzing'
  | 'configuring'
  | 'staging'
  | 'reviewing'
  | 'processing'
  | 'completed'
  | 'failed'

/** A column detected in the uploaded file during analysis. */
export interface DetectedColumn {
  /**
   * Deterministic, lossless mapping key (backend ColumnAnalysis::columnKeys):
   * the bare header name on first occurrence, `"{name}#{index}"` on a duplicate
   * header. `column_mapping`/`suggested_mapping` are keyed by THIS, never by
   * `name`, so two same-named columns stay independently mappable.
   */
  key: string
  name: string
  index: number
  duplicate: boolean
}

/** A field of the domain definition mappable from a file column. */
export interface ImportFieldDescriptor {
  id: string
  label: string
  required: boolean
  group: string
  type: string
}

/** A global (per-run, not per-row) configuration field of the domain definition. */
export interface ImportGlobalFieldDescriptor {
  id: string
  label: string
  required: boolean
  for_select_resource: string | null
  default: string | number | null
}

/** Mapping target sentinel: the column is intentionally not imported. */
export const IGNORE_TARGET = '__ignore__'
/** Mapping target sentinel: the column feeds `extra_fields` under its original name. */
export const EXTRA_TARGET = '__extra__'

/**
 * Fields shared by every response that carries a run: the create (201) and
 * configure/confirm (200) responses. `GET .../{importRun}` extends this with
 * the wizard-only fields below (`ImportRunDetail`).
 */
export interface ImportRunSummary {
  id: number
  resource: string
  status: ImportRunStatus
  original_filename: string
  total_rows: number
  valid_rows: number
  warning_rows: number
  error_rows: number
  duplicate_rows: number
  imported_rows: number | null
  modified_rows: number
  has_error_report: boolean
  created_at: string
}

/** Response shape of `GET /imports/{domain}/{importRun}` (envelope `data.import_run`). */
export interface ImportRunDetail extends ImportRunSummary {
  error_count: number
  detected_columns: DetectedColumn[] | null
  /** Column name (the file header, as returned in `detected_columns[].name`) -> field id | sentinel. */
  column_mapping: Record<string, string> | null
  global_config: Record<string, string | number | null> | null
  dedup_strategy: string | null
  /** Auto-map suggestion, present only from `configuring` onward. */
  suggested_mapping: Record<string, string> | null
  fields: ImportFieldDescriptor[]
  global_fields: ImportGlobalFieldDescriptor[]
  dedup_modes: string[]
  /**
   * The FINAL persisted fields the review grid shows/edits (spec 0033 delta
   * D-2026-07-15-placeholder-review-fields), e.g. `first_name`/`last_name`
   * instead of the input-only `full_name`. `label` is a default-namespace
   * i18n key, mirroring `ImportFieldDescriptor.label`. Optional/possibly
   * empty for backward compatibility with runs/fixtures predating the
   * delta — callers fall back to the mapped-fields derivation.
   */
  review_fields?: Array<{ id: string; label: string }>
  /**
   * A saved mapping template whose `columns` (spec 0035) match, in exact
   * order, this run's detected columns — computed SERVER-SIDE, never by the
   * client. Optional/possibly absent for backward compatibility with
   * fixtures predating the delta, mirroring `review_fields` above.
   */
  matching_template?: {
    id: number
    name: string
    column_mapping: Record<string, string>
    dedup_strategy: string | null
  } | null
}

/** A saved column-mapping template (spec 0035), shared team-wide per domain. */
export interface ImportMappingTemplate {
  id: number
  name: string
  /** Ordered snapshot of the source run's column keys — the exact-match signature. */
  columns: string[]
  column_mapping: Record<string, string>
  dedup_strategy: string | null
  created_by: { id: number; name: string }
  created_at: string
}

/** Body of `PUT /imports/{domain}/{importRun}/configure`. */
export interface ConfigureImportPayload {
  column_mapping: Record<string, string>
  global_config: Record<string, string | number | null>
  dedup_strategy: string
}

/** Status of a single staged row, mirrored from `App\Enums\ImportRowStatus`. */
export type ImportRowStatus = 'valid' | 'warning' | 'error' | 'duplicate' | 'skipped'

/** A single staged row, as returned by the review SSRM datasource / row update. */
export interface ImportRunRowItem {
  id: number
  row_number: number
  status: ImportRowStatus
  is_edited: boolean
  duplicate_of_id: number | null
  values: Record<string, string>
  messages: string[]
}

/** Response shape of `POST /imports/{domain}/{importRun}/rows` (envelope `data`). */
export interface ImportRunRowsPage {
  items: ImportRunRowItem[]
  pagination: { total: number; offset: number; limit: number; total_pages: number }
}

/** SSRM-style query body of `POST /imports/{domain}/{importRun}/rows`. */
export interface ImportRunRowsQuery {
  startRow: number
  endRow: number
  sortModel?: Array<{ colId: string; sort: 'asc' | 'desc' }>
  filterModel?: Record<string, unknown>
  search?: string
}

/** Counts returned alongside a row update, reflecting the run's recalculated totals. */
export interface ImportRunRowCounts {
  total: number
  valid_rows: number
  warning_rows: number
  error_rows: number
  duplicate_rows: number
  modified_rows: number
}

/** Response shape of `PATCH /imports/{domain}/{importRun}/rows/{row}` (envelope `data`). */
export interface ImportRunRowUpdateResult {
  row: ImportRunRowItem
  counts: ImportRunRowCounts
}

/** Response shape of `GET /imports/{domain}/{importRun}/summary` (envelope `data.summary`). */
export interface ImportRunSummaryReport {
  total_rows: number
  valid_rows: number
  warning_rows: number
  error_rows: number
  duplicate_rows: number
  modified_rows: number
  mapped_fields: Array<{ column: string; field: string }>
  extra_fields: string[]
  global_config: Record<string, unknown>
  dedup_strategy: string | null
  warnings: string[]
}

