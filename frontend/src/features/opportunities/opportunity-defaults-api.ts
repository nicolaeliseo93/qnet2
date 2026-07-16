import type { QueryClient } from '@tanstack/react-query'
import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { OpportunityDefaults } from '@/features/opportunities/types'

/**
 * Query key of a lead's opportunity-creation defaults (spec 0040 MT-6, BR-1).
 * Kept in its own small namespace (not `opportunityDetailQueryKey`): this is
 * never an opportunity's own detail, just the derived-values preview that
 * seeds the create-from-lead form.
 */
export function opportunityDefaultsQueryKey(leadId: number) {
  return ['opportunities', 'defaults', leadId] as const
}

/**
 * Fetches `GET /leads/{lead}/opportunity-defaults`: the values a new
 * Opportunity would inherit from the lead's campaign (BR-1), which of them
 * are locked (BR-2), and whether the lead already has one (D-2). The
 * endpoint lives under `/leads` (route model binding on the lead), but its
 * shape and sole purpose are entirely about creating an Opportunity, so the
 * wrapper is co-located with the rest of this feature rather than `features/leads`.
 */
export async function fetchOpportunityDefaults(leadId: number): Promise<OpportunityDefaults> {
  const { data } = await apiClient.get<ApiResponse<OpportunityDefaults>>(
    `/leads/${leadId}/opportunity-defaults`,
  )
  return data.data
}

/**
 * Imperative one-shot read of a lead's opportunity-defaults, run as a direct
 * consequence of the user picking a lead in the in-form "Lead" select (spec
 * 0040 A-1, AC-086) — never a render-time effect. Shares the exact same cache
 * entry as {@link useOpportunityDefaults} (`opportunityDefaultsQueryKey`), so
 * picking the same lead the deep-link already resolved is an instant
 * cache-hit, not a duplicate request.
 */
export function fetchOpportunityDefaultsOnce(
  queryClient: QueryClient,
  leadId: number,
): Promise<OpportunityDefaults> {
  return queryClient.fetchQuery({
    queryKey: opportunityDefaultsQueryKey(leadId),
    queryFn: () => fetchOpportunityDefaults(leadId),
  })
}
