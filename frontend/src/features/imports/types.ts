/**
 * Generic per-table CSV import types (spec 0012). The feature is parametrized
 * on a `domain` string (e.g. `companies`); every shape below matches the
 * frozen backend contract 1:1 (`data_contract` of the spec) so no field is
 * invented here.
 */

/** Lifecycle of an import run, mirrored from `App\Enums\ImportStatus`. */
export type ImportStatus =
  | 'validating'
  | 'awaiting_confirmation'
  | 'processing'
  | 'completed'
  | 'failed'

/** The import run resource returned by every endpoint. */
export interface ImportRun {
  id: number
  resource: string
  status: ImportStatus
  original_filename: string
  total_rows: number
  valid_rows: number
  invalid_rows: number
  /** Null until phase 2 (`ProcessImportJob`) completes. */
  imported_rows: number | null
  has_error_report: boolean
  created_at: string
}

/** A single row discarded during validation, with its reason(s). */
export interface ImportInvalidRow {
  row_number: number
  values: Record<string, string>
  errors: string[]
}

/**
 * Dry-run preview. Present from `awaiting_confirmation` onward, `null`
 * before. `valid_sample`/`invalid_sample` are bounded samples, never the full
 * dataset (see `IMPORT_PREVIEW_VALID`/`IMPORT_PREVIEW_INVALID` server-side).
 */
export interface ImportPreview {
  columns: string[]
  valid_sample: Array<Record<string, string>>
  invalid_sample: ImportInvalidRow[]
}

/** Response shape of `GET /imports/{domain}/{importRun}` (envelope `data`). */
export interface ImportRunDetail {
  import_run: ImportRun
  preview: ImportPreview | null
}
