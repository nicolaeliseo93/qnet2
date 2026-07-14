import { useCallback, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { forSelectKeys } from '@/features/for-select/query-keys'
import type { RelationFieldRef } from '@/components/form/relation-select-field'

/**
 * Accumulates the refs created via quick-create for a given resource, so the
 * caller's select can keep showing them as selected even before they surface
 * in the (invalidated) options page, and invalidates that resource's
 * for-select cache on every creation so the next fetch includes it (spec
 * 0028 AC-005/AC-006/AC-010).
 */
export function useQuickCreated(resource: string): {
  quickCreated: RelationFieldRef[]
  handleCreated: (ref: RelationFieldRef) => void
} {
  const queryClient = useQueryClient()
  const [quickCreated, setQuickCreated] = useState<RelationFieldRef[]>([])

  const handleCreated = useCallback(
    (ref: RelationFieldRef) => {
      // Step 1: remember the ref so the caller's select can hydrate it as
      // selected even if it falls outside the first options page.
      setQuickCreated((current) => [...current, ref])
      // Step 2: invalidate the resource's for-select cache so the freshly
      // created record is picked up without a page reload.
      void queryClient.invalidateQueries({ queryKey: forSelectKeys.resource(resource) })
    },
    [queryClient, resource],
  )

  return { quickCreated, handleCreated }
}
