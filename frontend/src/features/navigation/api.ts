import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { NavigationItem } from '@/features/navigation/types'

export async function fetchNavigation(): Promise<NavigationItem[]> {
  const { data } = await apiClient.get<ApiResponse<NavigationItem[]>>('/navigation')
  return data.data
}
