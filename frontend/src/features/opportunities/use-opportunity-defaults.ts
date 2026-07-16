import { useQuery } from '@tanstack/react-query'
import {
  fetchOpportunityDefaults,
  opportunityDefaultsQueryKey,
} from '@/features/opportunities/opportunity-defaults-api'

/**
 * Loads the create-from-lead defaults (spec 0040 MT-6) for `/opportunities/new?lead_id=N`.
 * `enabled` only when a valid `lead_id` is present — a plain manual create
 * never issues this request.
 */
export function useOpportunityDefaults(leadId: number | null) {
  return useQuery({
    queryKey: opportunityDefaultsQueryKey(leadId ?? -1),
    queryFn: () => fetchOpportunityDefaults(leadId as number),
    enabled: leadId !== null,
  })
}
