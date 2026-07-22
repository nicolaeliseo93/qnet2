import { useCallback } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchImpersonation,
  impersonateUser as impersonateUserRequest,
  stopImpersonation as stopImpersonationRequest,
} from '@/features/auth/api'
import { authKeys } from '@/features/auth/query-keys'
import type { LoginResult } from '@/features/auth/types'

interface UseImpersonationActionsParams {
  token: string | null
  setToken: (value: string | null) => void
}

/**
 * Encapsulates the impersonation start/stop flow so `AuthProvider` stays thin.
 * Both directions swap the session to a brand new identity: the token changes,
 * and every cache entry tied to the previous identity is dropped so nothing
 * of it can leak into the new session (spec 0050 frontend_contract).
 */
export function useImpersonationActions({ token, setToken }: UseImpersonationActionsParams) {
  const queryClient = useQueryClient()

  const impersonationQuery = useQuery({
    queryKey: authKeys.impersonation,
    queryFn: fetchImpersonation,
    enabled: Boolean(token),
    retry: false,
  })

  const switchIdentity = useCallback(
    (result: LoginResult) => {
      // Step 1: point the API client at the new token.
      setToken(result.token)
      // Step 2: drop every cached response tied to the previous identity.
      queryClient.clear()
      // Step 3: repopulate `me` immediately (no loader flash); `abilities` and
      // `impersonation` are still-mounted, enabled observers, so clearing the
      // cache alone is enough to make them refetch under the new token.
      queryClient.setQueryData(authKeys.me, result.user)
    },
    [queryClient, setToken],
  )

  const startMutation = useMutation({
    mutationFn: impersonateUserRequest,
    onSuccess: switchIdentity,
  })

  const stopMutation = useMutation({
    mutationFn: stopImpersonationRequest,
    onSuccess: switchIdentity,
  })

  const impersonate = useCallback(
    async (userId: number) => {
      await startMutation.mutateAsync(userId)
    },
    [startMutation],
  )

  const stopImpersonation = useCallback(async () => {
    await stopMutation.mutateAsync()
  }, [stopMutation])

  return {
    impersonator: impersonationQuery.data?.impersonator ?? null,
    impersonate,
    stopImpersonation,
  }
}
