import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import type { UseFormSetValue } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { fetchOpportunityDefaultsOnce } from '@/features/opportunities/opportunity-defaults-api'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/**
 * The 5 BR-1 fields a lead selection writes to (or clears from) the form.
 * `referent_id` is NOT among them (spec 0041 D-3): it stays a free,
 * anagrafica-scoped pick (BR-4 spec 0040), never derived/locked by the lead.
 */
const DERIVED_FIELDS = [
  'source_id',
  'operational_site_id',
  'registry_id',
  'business_function_id',
  'product_category_id',
] as const

export interface OpportunityLeadSelectionState {
  leadId: number | null
  /** BR-2: keys locked by the current selection. Empty while none is selected, or while `existingOpportunityId` blocks the flow. */
  lockedFields: string[]
  /** D-2: the lead is already linked to another opportunity — the form must block the submit, never write derived values. */
  existingOpportunityId: number | null
  /** The lead's anagrafica (its identity, spec 0041 D-3): the single source for both the Lead select's own trigger label and the registry picker's. */
  registry: RelationFieldRef | null
  /** True while the one-shot defaults fetch triggered by a fresh selection is in flight. */
  isApplying: boolean
  /** The defaults fetch failed; the selection was rolled back to "none". */
  isError: boolean
}

const EMPTY_STATE: OpportunityLeadSelectionState = {
  leadId: null,
  lockedFields: [],
  existingOpportunityId: null,
  registry: null,
  isApplying: false,
  isError: false,
}

export interface OpportunityLeadSelectionInitial {
  leadId: number
  lockedFields: string[]
  registry: RelationFieldRef | null
}

/**
 * Owns the in-form "Lead" picker's state (spec 0040 A-1, AC-086/087/088):
 * picking a lead applies BR-1's derived values and BR-2's locks EXACTLY like
 * the `?lead_id=N` deep-link — same one-shot fetch, same cache entry as
 * `useOpportunityDefaults` (`fetchOpportunityDefaultsOnce`), no second
 * implementation. Clearing the selection resets and unlocks the 5 derived
 * fields (the least surprising behavior: a field that no longer has a source
 * of truth should not silently keep looking "derived"). A lead already linked
 * to another opportunity (D-2) is surfaced without ever writing to the form.
 */
export function useOpportunityLeadSelection(
  initial: OpportunityLeadSelectionInitial | null,
  setValue: UseFormSetValue<OpportunityFormValues>,
) {
  const queryClient = useQueryClient()
  const [state, setState] = useState<OpportunityLeadSelectionState>(() =>
    initial
      ? {
          ...EMPTY_STATE,
          leadId: initial.leadId,
          lockedFields: initial.lockedFields,
          registry: initial.registry,
        }
      : EMPTY_STATE,
  )

  const clearDerivedFields = () => {
    for (const field of DERIVED_FIELDS) {
      setValue(field, null, { shouldDirty: true })
    }
  }

  const selectLead = async (leadId: number | null) => {
    if (leadId === null) {
      clearDerivedFields()
      setState(EMPTY_STATE)
      return
    }

    setState({ ...EMPTY_STATE, leadId, isApplying: true })
    try {
      const defaults = await fetchOpportunityDefaultsOnce(queryClient, leadId)

      if (defaults.existing_opportunity_id !== null) {
        setState({
          ...EMPTY_STATE,
          leadId,
          existingOpportunityId: defaults.existing_opportunity_id,
          registry: defaults.references.registry,
        })
        return
      }

      setValue('source_id', defaults.values.source_id, { shouldDirty: true })
      setValue('operational_site_id', defaults.values.operational_site_id, { shouldDirty: true })
      setValue('registry_id', defaults.values.registry_id, { shouldDirty: true })
      setValue('business_function_id', defaults.values.business_function_id, { shouldDirty: true })
      setValue('product_category_id', defaults.values.product_category_id, { shouldDirty: true })

      setState({
        leadId,
        lockedFields: defaults.locked_fields,
        existingOpportunityId: null,
        registry: defaults.references.registry,
        isApplying: false,
        isError: false,
      })
    } catch {
      setState({ ...EMPTY_STATE, isError: true })
    }
  }

  return { state, selectLead }
}
