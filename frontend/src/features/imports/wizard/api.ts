import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  ConfigureImportPayload,
  ImportRunDetail,
  ImportRunRowsPage,
  ImportRunRowsQuery,
  ImportRunRowUpdateResult,
  ImportRunHistoryPage,
  ImportRunSummary,
  ImportRunSummaryReport,
} from '@/features/imports/wizard/types'

/**
 * Uploads a file to start a wizard import run (`POST /imports/{domain}`).
 * Multipart body: axios infers the `multipart/form-data` boundary from the
 * `FormData` instance, so no `Content-Type` is set here (never force one).
 */
export async function analyzeImport(domain: string, file: File): Promise<ImportRunSummary> {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await apiClient.post<ApiResponse<{ import_run: ImportRunSummary }>>(
    `/imports/${domain}`,
    formData,
  )
  return data.data.import_run
}

/**
 * Polls/loads the full wizard state of a run (`GET /imports/{domain}/{id}`):
 * status, detected columns, mapping, global config and the mappable field
 * catalog. The single source of truth the wizard resumes from on reload.
 */
export async function getImportWizardRun(domain: string, importRunId: number): Promise<ImportRunDetail> {
  const { data } = await apiClient.get<ApiResponse<{ import_run: ImportRunDetail }>>(
    `/imports/${domain}/${importRunId}`,
  )
  return data.data.import_run
}

/**
 * Persists the column mapping, global config and dedup strategy, moving the
 * run from `configuring` to `staging` (`PUT .../configure`). Valid only
 * while `status === 'configuring'`.
 */
export async function configureImportRun(
  domain: string,
  importRunId: number,
  payload: ConfigureImportPayload,
): Promise<ImportRunSummary> {
  const { data } = await apiClient.put<ApiResponse<{ import_run: ImportRunSummary }>>(
    `/imports/${domain}/${importRunId}/configure`,
    payload,
  )
  return data.data.import_run
}

/**
 * Confirms a reviewed run, moving it to background processing
 * (`POST .../confirm`). Valid only while `status === 'reviewing'`.
 */
export async function confirmImportRun(domain: string, importRunId: number): Promise<ImportRunSummary> {
  const { data } = await apiClient.post<ApiResponse<{ import_run: ImportRunSummary }>>(
    `/imports/${domain}/${importRunId}/confirm`,
  )
  return data.data.import_run
}

/**
 * Lists the actor's own runs for a domain, paginated (`GET /imports/{domain}`).
 * Owned by F5 (history); exposed here so the endpoint signature is defined
 * exactly once.
 */
export async function getImportRunHistory(
  domain: string,
  page: number,
  perPage: number,
): Promise<ImportRunHistoryPage> {
  // Paginated list endpoints use the shared FLAT envelope ({ items, pagination,
  // export_link }) of BaseApiController::paginatedResponse — mirroring the
  // tables SSRM datasource (features/table/api.ts::fetchTableRows) — NOT the
  // { data } wrapper. Read the body directly.
  const { data } = await apiClient.get<ImportRunHistoryPage>(`/imports/${domain}`, {
    params: { page, per_page: perPage },
  })
  return data
}

/**
 * SSRM datasource of the staged-rows review grid (`POST .../rows`). Owned by
 * F2 (review step); exposed here so the request/response shape is defined
 * exactly once against the frozen contract.
 */
export async function getImportRunRows(
  domain: string,
  importRunId: number,
  query: ImportRunRowsQuery,
): Promise<ImportRunRowsPage> {
  // FLAT paginated envelope ({ items, pagination }) via paginatedResponse, like
  // the tables SSRM datasource — not the { data } wrapper. Read body directly.
  const { data } = await apiClient.post<ImportRunRowsPage>(
    `/imports/${domain}/${importRunId}/rows`,
    query,
  )
  return data
}

/**
 * Inline edit of a single staged row (`PATCH .../rows/{row}`), re-validated
 * server-side. Owned by F2 (review step).
 */
export async function updateImportRunRow(
  domain: string,
  importRunId: number,
  rowId: number,
  values: Record<string, string>,
): Promise<ImportRunRowUpdateResult> {
  const { data } = await apiClient.patch<ApiResponse<ImportRunRowUpdateResult>>(
    `/imports/${domain}/${importRunId}/rows/${rowId}`,
    { values },
  )
  return data.data
}

/**
 * Pre-confirm summary of a reviewing run (`GET .../summary`). Owned by F3
 * (summary step).
 */
export async function getImportRunSummary(
  domain: string,
  importRunId: number,
): Promise<ImportRunSummaryReport> {
  const { data } = await apiClient.get<ApiResponse<{ summary: ImportRunSummaryReport }>>(
    `/imports/${domain}/${importRunId}/summary`,
  )
  return data.data.summary
}
