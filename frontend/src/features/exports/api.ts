import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { filenameFromContentDisposition, saveBlob } from '@/lib/download'
import type { CreateExportPayload, ExportRun } from '@/features/exports/types'

/**
 * Creates an export run and dispatches the backend generation job
 * (`POST /exports/{domain}`). The payload mirrors the grid's current state
 * exactly (columns, sort, filters, search) — the frontend never recomputes
 * export logic, it only reports state.
 */
export async function createExport(
  domain: string,
  payload: CreateExportPayload,
): Promise<ExportRun> {
  const { data } = await apiClient.post<ApiResponse<{ export_run: ExportRun }>>(
    `/exports/${domain}`,
    payload,
  )
  return data.data.export_run
}

/** Polls the current state of an export run (`GET /exports/{domain}/{id}`). */
export async function getExportRun(domain: string, exportRunId: number): Promise<ExportRun> {
  const { data } = await apiClient.get<ApiResponse<{ export_run: ExportRun }>>(
    `/exports/${domain}/${exportRunId}`,
  )
  return data.data.export_run
}

/**
 * Downloads the generated file for a completed run
 * (`GET /exports/{domain}/{id}/download`).
 */
export async function downloadExport(domain: string, exportRunId: number): Promise<void> {
  const response = await apiClient.get<Blob>(`/exports/${domain}/${exportRunId}/download`, {
    responseType: 'blob',
  })
  const filename =
    filenameFromContentDisposition(response.headers['content-disposition']) ??
    `${domain}-export-${exportRunId}`
  saveBlob(response.data, filename)
}
