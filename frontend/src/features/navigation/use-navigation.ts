import { useQuery } from '@tanstack/react-query'
import { fetchNavigation } from '@/features/navigation/api'
import { useAuth } from '@/features/auth/use-auth'

export const navigationKeys = {
  all: ['navigation'] as const,
}

export function useNavigation() {
  const { isAuthenticated } = useAuth()

  return useQuery({
    queryKey: navigationKeys.all,
    queryFn: fetchNavigation,
    enabled: isAuthenticated,
    staleTime: 5 * 60 * 1000,
  })
}
