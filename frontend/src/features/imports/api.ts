import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { filenameFromContentDisposition, saveBlob } from '@/lib/download'
import type { ImportRun, ImportRunDetail } from '@/features/imports/types'

/**
 * Downloads the fixed-header CSV template for a domain
 * (`GET /imports/{domain}/template`).
 */
export async function downloadImportTemplate(domain: string): Promise<void> {
  const response = await apiClient.get<Blob>(`/imports/${domain}/template`, {
    responseType: 'blob',
  })
  const filename =
    filenameFromContentDisposition(response.headers['content-disposition']) ??
    `${domain}-import-template.csv`
  saveBlob(response.data, filename)
}

/**
 * Uploads a CSV file to start an import run (`POST /imports/{domain}`).
 * Multipart body: axios infers the `multipart/form-data` boundary from the
 * `FormData` instance, so no `Content-Type` is set here (never force one).
 */
export async function uploadImport(domain: string, file: File): Promise<ImportRun> {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await apiClient.post<ApiResponse<{ import_run: ImportRun }>>(
    `/imports/${domain}`,
    formData,
  )
  return data.data.import_run
}

/**
 * Polls the current state of an import run (`GET /imports/{domain}/{id}`).
 * Returns both the run and, from `awaiting_confirmation` onward, its preview.
 */
export async function getImportRun(
  domain: string,
  importRunId: number,
): Promise<ImportRunDetail> {
  const { data } = await apiClient.get<ApiResponse<ImportRunDetail>>(
    `/imports/${domain}/${importRunId}`,
  )
  return data.data
}

/**
 * Confirms a validated import, moving it to background processing
 * (`POST /imports/{domain}/{id}/confirm`). Only valid from
 * `awaiting_confirmation` (422 otherwise).
 */
export async function confirmImport(domain: string, importRunId: number): Promise<ImportRun> {
  const { data } = await apiClient.post<ApiResponse<{ import_run: ImportRun }>>(
    `/imports/${domain}/${importRunId}/confirm`,
  )
  return data.data.import_run
}

/**
 * Downloads the full error report CSV for a run
 * (`GET /imports/{domain}/{id}/errors`), containing every discarded row (not
 * just the preview sample).
 */
export async function downloadImportErrorReport(
  domain: string,
  importRunId: number,
): Promise<void> {
  const response = await apiClient.get<Blob>(`/imports/${domain}/${importRunId}/errors`, {
    responseType: 'blob',
  })
  const filename =
    filenameFromContentDisposition(response.headers['content-disposition']) ??
    `${domain}-import-errors-${importRunId}.csv`
  saveBlob(response.data, filename)
}
