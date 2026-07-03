import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  MigrationColumn,
  MigrationPreviewPage,
  MigrationRun,
  MigrationRunCreated,
  MigrationSourceSummary,
} from '@/features/migrations/types'

/** Fetches the registered migration sources (`GET /migrations`). */
export async function fetchMigrationSources(): Promise<MigrationSourceSummary[]> {
  const { data } = await apiClient.get<ApiResponse<{ sources: MigrationSourceSummary[] }>>(
    '/migrations',
  )
  return data.data.sources
}

/** Fetches the columns exposed by a source (`GET /migrations/{source}/columns`). */
export async function fetchMigrationColumns(source: string): Promise<MigrationColumn[]> {
  const { data } = await apiClient.get<ApiResponse<{ columns: MigrationColumn[] }>>(
    `/migrations/${source}/columns`,
  )
  return data.data.columns
}

/**
 * Fetches one page of a source's read-only external preview (fase 1)
 * (`GET /migrations/{source}/preview`).
 */
export async function fetchMigrationPreview(
  source: string,
  page: number,
  perPage: number,
): Promise<MigrationPreviewPage> {
  const { data } = await apiClient.get<ApiResponse<MigrationPreviewPage>>(
    `/migrations/${source}/preview`,
    { params: { page, per_page: perPage } },
  )
  return data.data
}

/**
 * Starts the queued import (fase 2) for a source
 * (`POST /migrations/{source}/import`).
 */
export async function startMigrationImport(source: string): Promise<MigrationRunCreated> {
  const { data } = await apiClient.post<ApiResponse<{ migration_run: MigrationRunCreated }>>(
    `/migrations/${source}/import`,
  )
  return data.data.migration_run
}

/**
 * Polls the current state of a run
 * (`GET /migrations/{source}/runs/{migrationRun}`).
 */
export async function fetchMigrationRun(source: string, runId: number): Promise<MigrationRun> {
  const { data } = await apiClient.get<ApiResponse<{ migration_run: MigrationRun }>>(
    `/migrations/${source}/runs/${runId}`,
  )
  return data.data.migration_run
}
