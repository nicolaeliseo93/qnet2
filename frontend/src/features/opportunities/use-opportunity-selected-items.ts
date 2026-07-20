import { useMemo } from 'react'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { OpportunityFormMode } from '@/features/opportunities/types'
import type { OpportunityLeadSelectionState } from '@/features/opportunities/use-opportunity-lead-selection'

/** Every relation picker's edit-mode hydration, resolved once by the form hook (mirrors `RegistrySelectedItems`). */
export interface OpportunitySelectedItems {
  registry: RelationFieldRef | null
  /** Spec 0043 D-3: the mandatory opportunity status, always set in edit mode. */
  opportunityStatus: RelationFieldRef | null
  referent: RelationFieldRef | null
  commercial: RelationFieldRef | null
  reporter: RelationFieldRef | null
  source: RelationFieldRef | null
  supervisor: RelationFieldRef | null
  managers: ForSelectItem[]
}

const EMPTY_SELECTED_ITEMS: OpportunitySelectedItems = {
  registry: null,
  opportunityStatus: null,
  referent: null,
  commercial: null,
  reporter: null,
  source: null,
  supervisor: null,
  managers: [],
}

/**
 * Resolves every relation picker's already-known `{id, name}` hydration: from
 * the loaded instance in edit mode, or from the Lead's derived `references`
 * (spec 0040 MT-6) in a create-from-lead — so each `AsyncPaginatedSelect`
 * shows its (possibly locked) current selection immediately, no hydration
 * round-trip. A plain manual create has nothing to hydrate. `business_function`/
 * `product_category` are NOT part of this shape anymore (spec 0040 amendment
 * rev.3): `OpportunityProductLinesField` resolves its own row labels.
 *
 * `leadSelection.registry` (spec 0041 AC-051) takes precedence over
 * `mode.fromLead.references.registry`: it reflects the in-form "Lead" picker,
 * which can supersede the initial deep-link lead after mount, while the
 * latter only ever hydrates the very first render. `supervisor` mirrors the
 * same precedence (spec 0044 AC-034): `leadSelection.supervisor` is set only
 * right after a fresh in-form selection actually prefilled the field, so it
 * naturally falls back to `references.supervisor` (deep-link mount) or `null`
 * (no prefill happened — an already-chosen Supervisor keeps its own label,
 * resolved by the picker itself, not by this hydration).
 */
export function useOpportunitySelectedItems(
  mode: OpportunityFormMode,
  leadSelection: OpportunityLeadSelectionState,
): OpportunitySelectedItems {
  return useMemo(() => {
    if (mode.type === 'edit') {
      const { opportunity } = mode
      return {
        registry: opportunity.registry,
        opportunityStatus: opportunity.opportunity_status,
        referent: opportunity.referent,
        commercial: opportunity.commercial,
        reporter: opportunity.reporter,
        source: opportunity.source,
        supervisor: opportunity.supervisor,
        managers: opportunity.managers.map((manager) => ({ id: manager.id, label: manager.name })),
      }
    }
    const references = mode.fromLead?.references
    if (!references && !leadSelection.registry) {
      return EMPTY_SELECTED_ITEMS
    }
    return {
      ...EMPTY_SELECTED_ITEMS,
      registry: leadSelection.registry ?? references?.registry ?? null,
      source: references?.source ?? null,
      supervisor: leadSelection.supervisor ?? references?.supervisor ?? null,
    }
  }, [mode, leadSelection.registry, leadSelection.supervisor])
}
