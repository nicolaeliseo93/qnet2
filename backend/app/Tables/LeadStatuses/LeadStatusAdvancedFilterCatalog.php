<?php

namespace App\Tables\LeadStatuses;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `lead-statuses` domain (spec 0032),
 * mirroring PipelineStatusAdvancedFilterCatalog 1:1: a small lookup table
 * (LeadStatusColumnCatalog: name/color/sort_order/group/created_at) — every
 * entry here is a direct-column filter, handled entirely by the generic
 * default (no domain override needed). `color` is deliberately left out,
 * mirroring the column catalogue's own choice to keep it neither sortable
 * nor filterable (a swatch value, not a meaningful filter axis). `group`
 * (spec 0039 pivot, App\Enums\StatusGroup) is ALSO left out here: it is
 * already reachable via the basic `set` column filter (LeadStatusColumnCatalog),
 * and no advanced-filter widget type in this catalogue's repertoire
 * (AdvancedFilterType) carries a static options list end-to-end — every
 * existing usage of Select/Enum in this codebase is unused, only Relation
 * (an id-based FK lookup) is — so adding a second, narrower path here would
 * be speculative (engineering.md §1.3).
 */
final class LeadStatusAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'name',
                'label' => 'leadStatuses.advancedFilters.name',
                'type' => AdvancedFilterType::Text,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'name',
            ],
            [
                'name' => 'sort_order_range',
                'label' => 'leadStatuses.advancedFilters.sortOrderRange',
                'type' => AdvancedFilterType::NumberRange,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'sort_order',
            ],
            [
                'name' => 'created_range',
                'label' => 'leadStatuses.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
        ];
    }
}
