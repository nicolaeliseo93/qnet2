<?php

namespace App\Tables\Campaigns;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `campaigns` domain (spec 0032). Curated
 * from the domain's own derived columns (CampaignColumnCatalog) and the
 * relations already eager-loaded by CampaignsTableDefinition::baseQuery() —
 * no invented column/relation. `target` is the relation accessor name
 * (generic whereHas-by-id via AdvancedFilterApplier) or the real DB column;
 * `pipeline_status` is the ONE doubly-derived entry (BR-2/AC-032: a linked
 * campaign's own status is NULL, its effective one reads through the
 * project) — its `target` is internal-only, handled by
 * CampaignsTableDefinition's applyAdvancedFilter() override delegating to
 * CampaignPipelineStatusResolver::applyIdFilter().
 */
final class CampaignAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'project',
                'label' => 'campaigns.advancedFilters.project',
                'type' => AdvancedFilterType::Relation,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'projects'],
                'target' => 'project',
            ],
            [
                'name' => 'pipeline_status',
                'label' => 'campaigns.advancedFilters.pipelineStatus',
                'type' => AdvancedFilterType::Relation,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'pipeline-statuses'],
                // Internal only: own-or-through-project (BR-2), handled by
                // the domain override — never a plain relation-by-id.
                'target' => 'pipelineStatus',
            ],
            [
                'name' => 'source',
                'label' => 'campaigns.advancedFilters.source',
                'type' => AdvancedFilterType::Relation,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'sources'],
                'target' => 'source',
            ],
            [
                'name' => 'partner',
                'label' => 'campaigns.advancedFilters.partner',
                'type' => AdvancedFilterType::Relation,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'referents'],
                'target' => 'partner',
            ],
            [
                'name' => 'budget_range',
                'label' => 'campaigns.advancedFilters.budgetRange',
                'type' => AdvancedFilterType::NumberRange,
                'order' => 5,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'total_budget',
            ],
            [
                'name' => 'created_range',
                'label' => 'campaigns.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 6,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
        ];
    }
}
