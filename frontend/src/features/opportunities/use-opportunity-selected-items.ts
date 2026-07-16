import { useMemo } from 'react'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { OpportunityFormMode } from '@/features/opportunities/types'

/** Every relation picker's edit-mode hydration, resolved once by the form hook (mirrors `RegistrySelectedItems`). */
export interface OpportunitySelectedItems {
  registry: RelationFieldRef | null
  referent: RelationFieldRef | null
  commercial: RelationFieldRef | null
  reporter: RelationFieldRef | null
  company: RelationFieldRef | null
  companySite: RelationFieldRef | null
  businessFunction: RelationFieldRef | null
  operationalSite: RelationFieldRef | null
  productCategory: RelationFieldRef | null
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
  businessFunction: null,
  operationalSite: null,
  productCategory: null,
  source: null,
  supervisor: null,
  managers: [],
}

/**
 * Resolves every relation picker's already-known `{id, name}` hydration: from
 * the loaded instance in edit mode, or from the Lead's derived `references`
 * (spec 0040 MT-6) in a create-from-lead — so each `AsyncPaginatedSelect`
 * shows its (possibly locked) current selection immediately, no hydration
 * round-trip. A plain manual create has nothing to hydrate.
 */
export function useOpportunitySelectedItems(mode: OpportunityFormMode): OpportunitySelectedItems {
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
        businessFunction: opportunity.business_function,
        operationalSite: opportunity.operational_site
          ? { id: opportunity.operational_site.id, name: opportunity.operational_site.label }
          : null,
        productCategory: opportunity.product_category,
        source: opportunity.source,
        supervisor: opportunity.supervisor,
        managers: opportunity.managers.map((manager) => ({ id: manager.id, label: manager.name })),
      }
    }
    if (!mode.fromLead) {
      return EMPTY_SELECTED_ITEMS
    }
    const { references } = mode.fromLead
    return {
      ...EMPTY_SELECTED_ITEMS,
      registry: references.registry,
      referent: references.referent,
      source: references.source,
      operationalSite: references.operational_site
        ? { id: references.operational_site.id, name: references.operational_site.label }
        : null,
      businessFunction: references.business_function,
      productCategory: references.product_category,
    }
  }, [mode])
}
