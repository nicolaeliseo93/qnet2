<?php

namespace App\Tables\OpportunityStatuses;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `opportunity-statuses` domain (spec
 * 0032/0043): a small lookup
 * table (OpportunityStatusColumnCatalog: name/color/sort_order/group/
 * created_at) — every entry here is a direct-column filter, handled entirely
 * by the generic default (no domain override needed). `color` is
 * deliberately left out, mirroring the column catalogue's own choice to
 * keep it neither sortable nor filterable (a swatch value, not a meaningful
 * filter axis). `group` is ALSO left out here: it is already reachable via
 * the basic `set` column filter (OpportunityStatusColumnCatalog), and no
 * advanced-filter widget type in this catalogue's repertoire
 * (AdvancedFilterType) carries a static options list end-to-end.
 */
final class OpportunityStatusAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'name',
                'label' => 'opportunityStatuses.advancedFilters.name',
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
                'label' => 'opportunityStatuses.advancedFilters.sortOrderRange',
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
                'label' => 'opportunityStatuses.advancedFilters.createdRange',
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
