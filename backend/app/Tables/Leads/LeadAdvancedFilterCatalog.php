<?php

namespace App\Tables\Leads;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `leads` domain (spec 0032). Curated from
 * the domain's own derived columns (LeadColumnCatalog) and the relations
 * already eager-loaded by LeadsTableDefinition::baseQuery() — no invented
 * column/relation. `target` is the relation accessor name (generic
 * whereHas-by-id via AdvancedFilterApplier for every `relation` entry) or the
 * real DB column (`created_at`). `operational_site` has no relation-by-id
 * equivalent (BR-3: the site has no own name) — it is a `text` search
 * delegated to LeadOperationalSiteColumn via LeadsTableDefinition's
 * applyAdvancedFilter() override.
 */
final class LeadAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'lead_status',
                'label' => 'leads.advancedFilters.leadStatus',
                'type' => AdvancedFilterType::Relation,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'lead-statuses'],
                'target' => 'leadStatus',
            ],
            [
                'name' => 'campaign',
                'label' => 'leads.advancedFilters.campaign',
                'type' => AdvancedFilterType::Relation,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'campaigns'],
                'target' => 'campaign',
            ],
            [
                'name' => 'referent',
                'label' => 'leads.advancedFilters.referent',
                'type' => AdvancedFilterType::Relation,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'referents'],
                'target' => 'referent',
            ],
            [
                'name' => 'source',
                'label' => 'leads.advancedFilters.source',
                'type' => AdvancedFilterType::Relation,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'sources'],
                'target' => 'source',
            ],
            [
                'name' => 'operator',
                'label' => 'leads.advancedFilters.operator',
                'type' => AdvancedFilterType::Relation,
                'order' => 5,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'users'],
                'target' => 'operator',
            ],
            [
                'name' => 'operational_site',
                'label' => 'leads.advancedFilters.operationalSite',
                'type' => AdvancedFilterType::Text,
                'order' => 6,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                // Internal only: no real column/relation-by-id (BR-3) — the
                // domain override searches the site's primary address line1.
                'target' => 'operational_site',
            ],
            [
                'name' => 'created_range',
                'label' => 'leads.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 7,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
        ];
    }
}
