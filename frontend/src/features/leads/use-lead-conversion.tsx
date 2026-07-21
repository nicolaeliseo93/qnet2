import type { ReactNode } from 'react'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { OPPORTUNITIES_DOMAIN } from '@/features/opportunities/api'

interface UseLeadConversionOptions {
  /** Ran after the linked Opportunity is saved (caller's grid refresh / detail invalidation). */
  onOpportunitySaved?: () => void
}

interface UseLeadConversionResult {
  /**
   * Entry point of the lead -> opportunity conversion: opens the prefilled
   * Opportunity form for the given lead id straight away. Directive
   * 2026-07-21: Operator and Site are no longer mandatory to convert (the
   * Opportunity simply inherits a null supervisor when the lead has no
   * Operator), so the former "complete the lead first" correction gate
   * (spec 0044, revised decision 2) is gone — every lead converts directly,
   * no extra fetch needed to read fields nobody gates on anymore.
   */
  startConversion: (leadId: number) => void
  /** Mount once in the caller's tree: the Opportunity opener's own Sheet/page. */
  sheets: ReactNode
}

/**
 * Shared controller for both conversion triggers (the leads table row action
 * and the lead detail page button): a thin, named wrapper over
 * `useModuleOpener('opportunities').openCreateWith` so both call sites share
 * one `sheets` mount point and one place to evolve the conversion entry point.
 */
export function useLeadConversion(options: UseLeadConversionOptions = {}): UseLeadConversionResult {
  const { onOpportunitySaved } = options

  const { openCreateWith: openOpportunityWith, sheet: opportunitySheet } = useModuleOpener(
    OPPORTUNITIES_DOMAIN,
    { onSaved: onOpportunitySaved },
  )

  const startConversion = (leadId: number) => {
    openOpportunityWith({ lead_id: leadId })
  }

  return { startConversion, sheets: opportunitySheet }
}
