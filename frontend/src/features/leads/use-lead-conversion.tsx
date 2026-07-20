import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { OPPORTUNITIES_DOMAIN } from '@/features/opportunities/api'
import { fetchLead, leadDetailQueryKey } from '@/features/leads/api'
import { LeadForm } from '@/features/leads/lead-form'
import type { LeadDetail, LeadDetailWithPermissions } from '@/features/leads/types'

interface UseLeadConversionOptions {
  /** Ran after the linked Opportunity is saved (caller's grid refresh / detail invalidation). */
  onOpportunitySaved?: () => void
  /** Ran after the lead is corrected in the interstitial step, before the Opportunity form opens. */
  onLeadCorrected?: () => void
}

interface UseLeadConversionResult {
  /**
   * Entry point of the lead -> opportunity conversion. Accepts an already
   * loaded lead (detail page) or its id (table row, fetched on demand). When
   * the lead still lacks the Operator or the operational Site — the two fields
   * the Opportunity derives its supervisor and scope from — it opens the lead
   * edit form first to correct them, then chains to the prefilled Opportunity
   * form (spec 0044, revised decision 2). A complete lead opens the
   * Opportunity form directly.
   */
  startConversion: (lead: number | LeadDetailWithPermissions) => Promise<void>
  /**
   * Mount once in the caller's tree: the correction popup (a centered Dialog,
   * always modal regardless of the leads open-mode preference) plus the
   * Opportunity opener's own Sheet/page.
   */
  sheets: ReactNode
}

/**
 * Shared controller for both conversion triggers (the leads table row action
 * and the lead detail page button). Centralizes the gate that used to be a
 * plain `openOpportunityWith`: a lead missing Operator or Site is completed
 * first, so the Opportunity is never born with an empty supervisor.
 */
export function useLeadConversion(options: UseLeadConversionOptions = {}): UseLeadConversionResult {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { onOpportunitySaved, onLeadCorrected } = options

  const { openCreateWith: openOpportunityWith, sheet: opportunitySheet } = useModuleOpener(
    OPPORTUNITIES_DOMAIN,
    { onSaved: onOpportunitySaved },
  )

  const [correctionLead, setCorrectionLead] = useState<LeadDetailWithPermissions | null>(null)
  const closeCorrection = useCallback(() => setCorrectionLead(null), [])

  const startConversion = useCallback(
    async (leadOrId: number | LeadDetailWithPermissions) => {
      // Step 1: resolve the authoritative lead (fetch only when given an id).
      const lead =
        typeof leadOrId === 'number'
          ? await queryClient.fetchQuery({
              queryKey: leadDetailQueryKey(leadOrId),
              queryFn: () => fetchLead(leadOrId),
            })
          : leadOrId

      // Step 2: gate on the fields the Opportunity depends on. `== null`
      // catches both null (real contract) and an absent key (partial fixtures).
      if (lead.operator_id == null || lead.operational_site_id == null) {
        setCorrectionLead(lead)
        return
      }

      // Step 3: ready lead -> straight to the prefilled Opportunity form.
      openOpportunityWith({ lead_id: lead.id })
    },
    [queryClient, openOpportunityWith],
  )

  const handleCorrected = useCallback(
    (saved: LeadDetail) => {
      setCorrectionLead(null)
      onLeadCorrected?.()
      openOpportunityWith({ lead_id: saved.id })
    },
    [onLeadCorrected, openOpportunityWith],
  )

  const sheets = (
    <>
      <Dialog open={correctionLead !== null} onOpenChange={(open) => !open && closeCorrection()}>
        <DialogContent className="flex max-h-[85vh] max-w-2xl flex-col gap-0 overflow-hidden p-0">
          <DialogHeader className="px-4 pt-4">
            <DialogTitle>{t('leads.conversion.correctTitle')}</DialogTitle>
            <DialogDescription>{t('leads.conversion.correctSubtitle')}</DialogDescription>
          </DialogHeader>
          {correctionLead && (
            <LeadForm
              mode={{ type: 'edit', lead: correctionLead }}
              requireConversionFields
              onSuccess={handleCorrected}
              onCancel={closeCorrection}
            />
          )}
        </DialogContent>
      </Dialog>
      {opportunitySheet}
    </>
  )

  return { startConversion, sheets }
}
