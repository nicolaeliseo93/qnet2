/**
 * External data migrations types (spec 0013): a super-admin only, two-phase
 * flow (read-only preview -> queued import) against the frozen
 * `/api/migrations` `data_contract`. Every shape below matches that contract
 * 1:1 -- no field is invented here.
 */

/** A registered migration source, as returned by `GET /migrations`. */
export interface MigrationSourceSummary {
  key: string
  label: string
}

/** A column exposed by a source's external records, driving the preview table. */
export interface MigrationColumn {
  id: string
  label: string
  type: 'string' | 'number' | 'boolean' | 'date'
}

/** A single external record row, keyed by column id. */
export type MigrationPreviewRow = Record<string, string | number | boolean | null>

export interface MigrationPreviewPagination {
  page: number
  per_page: number
  total: number | null
  has_more: boolean
}

/** Response shape of `GET /migrations/{source}/preview` (envelope `data`). */
export interface MigrationPreviewPage {
  rows: MigrationPreviewRow[]
  pagination: MigrationPreviewPagination
}

/** Lifecycle of a migration run, mirrored from `App\Enums\MigrationStatus`. */
export type MigrationRunStatus = 'pending' | 'processing' | 'completed' | 'failed'

/** The run resource as returned right after `POST /migrations/{source}/import` (201). */
export interface MigrationRunCreated {
  id: number
  source: string
  status: 'pending'
  total_rows: number
  created_rows: number
  skipped_rows: number
  failed_rows: number
  has_report: boolean
  created_at: string
}

/** A single warning/error entry accumulated per row during the import. */
export interface MigrationReportEntry {
  old_id: number | string | null
  level: 'warning' | 'error'
  message: string
}

/** The run resource as returned by the polling endpoint `GET .../runs/{migrationRun}`. */
export interface MigrationRun {
  id: number
  source: string
  status: MigrationRunStatus
  total_rows: number
  created_rows: number
  skipped_rows: number
  failed_rows: number
  report: MigrationReportEntry[] | null
  created_at: string
}
