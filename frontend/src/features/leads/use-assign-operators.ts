import { useMutation } from '@tanstack/react-query'
import { assignLeadOperators } from '@/features/leads/api'
import type { AssignOperatorsPayload, AssignOperatorsResult } from '@/features/leads/types'

interface UseAssignOperatorsOptions {
  /** Ran after a successful assignment; the caller drives its own refresh/toast (spec 0048 AC-041). */
  onSuccess?: (result: AssignOperatorsResult) => void
}

/**
 * Thin `useMutation` wrapper over `assignLeadOperators` (spec 0048), shared by
 * every consumer of `AssignOperatorsDialog`. Deliberately generic: it neither
 * invalidates a query nor toasts — each consumer (the Lead table, the import
 * review bar) owns its own post-success refresh, since they read from
 * different caches.
 */
export function useAssignOperators({ onSuccess }: UseAssignOperatorsOptions = {}) {
  return useMutation({
    mutationFn: (payload: AssignOperatorsPayload) => assignLeadOperators(payload),
    onSuccess,
  })
}
