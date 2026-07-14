import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { ModuleStats } from '@/features/stats/types'

/** Query key of a module's statistics, scoped by domain (spec 0026). */
export function moduleStatsQueryKey(domain: string) {
  return ['stats', domain] as const
}

/** Fetches the widgets a module exposes. 403/404 surface as Axios errors. */
export async function fetchModuleStats(domain: string): Promise<ModuleStats> {
  const { data } = await apiClient.get<ApiResponse<ModuleStats>>(`/stats/${domain}`)
  return data.data
}
