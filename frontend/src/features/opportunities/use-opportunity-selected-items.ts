import { useMemo } from 'react'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { OpportunityFormMode } from '@/features/opportunities/types'
import type { OpportunityLeadSelectionState } from '@/features/opportunities/use-opportunity-lead-selection'

/** Every relation picker's edit-mode hydration, resolved once by the form hook (mirrors `RegistrySelectedItems`). */
export interface OpportunitySelectedItems {
  registry: RelationFieldRef | null
  referent: RelationFieldRef | null
  commercial: RelationFieldRef | null
  reporter: RelationFieldRef | null
  company: RelationFieldRef | null
  companySite: RelationFieldRef | null
  operationalSite: RelationFieldRef | null
  source: RelationFieldRef | null
  supervisor: RelationFieldRef | null
  managers: ForSelectItem[]
}

const EMPTY_SELECTED_ITEMS: OpportunitySelectedItems = {
  registry: null,
  referent: null,
  commercial: null,
  reporter: null,
  company: null,
  companySite: null,
  operationalSite: null,
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
 * latter only ever hydrates the very first render.
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
        referent: opportunity.referent,
        commercial: opportunity.commercial,
        reporter: opportunity.reporter,
        company: opportunity.company,
        companySite: opportunity.company_site,
        operationalSite: opportunity.operational_site
          ? { id: opportunity.operational_site.id, name: opportunity.operational_site.label }
          : null,
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
      operationalSite: references?.operational_site
        ? { id: references.operational_site.id, name: references.operational_site.label }
        : null,
    }
  }, [mode, leadSelection.registry])
}
