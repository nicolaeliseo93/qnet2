<?php

namespace App\Tables\Opportunities;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `opportunities` domain (spec 0032/0040).
 * Curated from the domain's own derived columns (OpportunityColumnCatalog)
 * and the relations already eager-loaded by
 * OpportunitiesTableDefinition::baseQuery() — no invented column/relation.
 * `target` is the relation accessor name (generic whereHas-by-id via
 * AdvancedFilterApplier for every `relation` entry) or the real DB column.
 * `product_category`/`business_function` (amendment rev.3) target a NESTED
 * dot-path (`productLines.productCategory`/`productLines.businessFunction`)
 * — AdvancedFilterApplier's `whereHas($target, ...)` call needs no change:
 * Eloquent's own `whereHas()` already supports dot-path nested relations.
 */
final class OpportunityAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'registry',
                'label' => 'opportunities.advancedFilters.registry',
                'type' => AdvancedFilterType::Relation,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'registries'],
                'target' => 'registry',
            ],
            [
                // spec 0043, D-3: the mandatory working-state classification.
                'name' => 'opportunity_status',
                'label' => 'opportunities.advancedFilters.opportunityStatus',
                'type' => AdvancedFilterType::Relation,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'opportunity-statuses'],
                'target' => 'opportunityStatus',
            ],
            [
                'name' => 'referent',
                'label' => 'opportunities.advancedFilters.referent',
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
                'name' => 'commercial',
                'label' => 'opportunities.advancedFilters.commercial',
                'type' => AdvancedFilterType::Relation,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'referents'],
                'target' => 'commercial',
            ],
            [
                'name' => 'supervisor',
                'label' => 'opportunities.advancedFilters.supervisor',
                'type' => AdvancedFilterType::Relation,
                'order' => 5,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'users'],
                'target' => 'supervisor',
            ],
            [
                'name' => 'source',
                'label' => 'opportunities.advancedFilters.source',
                'type' => AdvancedFilterType::Relation,
                'order' => 6,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'sources'],
                'target' => 'source',
            ],
            [
                'name' => 'product_category',
                'label' => 'opportunities.advancedFilters.productCategory',
                'type' => AdvancedFilterType::Relation,
                'order' => 7,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'product-categories'],
                'target' => 'productLines.productCategory',
            ],
            [
                'name' => 'business_function',
                'label' => 'opportunities.advancedFilters.businessFunction',
                'type' => AdvancedFilterType::Relation,
                'order' => 8,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'business-functions'],
                'target' => 'productLines.businessFunction',
            ],
            [
                'name' => 'value_range',
                'label' => 'opportunities.advancedFilters.valueRange',
                'type' => AdvancedFilterType::NumberRange,
                'order' => 9,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'estimated_value',
            ],
            [
                'name' => 'created_range',
                'label' => 'opportunities.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 10,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
        ];
    }
}
