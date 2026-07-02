import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { AppConfig } from '@/features/config/types'

/**
 * Fetches the public application bootstrap config (enum options, ...). The
 * endpoint is unauthenticated; the standard envelope is unwrapped to `data`.
 */
export async function fetchConfig(): Promise<AppConfig> {
  const { data } = await apiClient.get<ApiResponse<AppConfig>>('/config')
  return data.data
}
