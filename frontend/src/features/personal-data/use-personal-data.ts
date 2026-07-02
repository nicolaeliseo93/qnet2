import { useQuery } from '@tanstack/react-query'
import { fetchCardByOwner } from '@/features/personal-data/api'
import { personalDataKeys } from '@/features/personal-data/query-keys'
import type { OwnerRef } from '@/features/personal-data/types'

/**
 * Loads an owner's personal-data card (with its contacts and addresses), or
 * null when the owner has no card yet. Owner-agnostic: pass any `OwnerRef`.
 *
 * `enabled` lets a caller defer the fetch until the owner actually exists (e.g.
 * the user-create flow, where the user id is not available yet).
 */
export function usePersonalDataByOwner(owner: OwnerRef, enabled = true) {
  return useQuery({
    queryKey: personalDataKeys.byOwner(owner),
    queryFn: () => fetchCardByOwner(owner),
    enabled,
  })
}
