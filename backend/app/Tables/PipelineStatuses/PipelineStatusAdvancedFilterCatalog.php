<?php

namespace App\Tables\PipelineStatuses;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `pipeline-statuses` domain (spec 0032). A
 * small lookup table (PipelineStatusColumnCatalog: name/color/sort_order/
 * group/created_at) — every entry here is a direct-column filter, handled
 * entirely by the generic default (no domain override needed). `color` is
 * deliberately left out, mirroring the column catalogue's own choice to
 * keep it neither sortable nor filterable (a swatch value, not a
 * meaningful filter axis). `group` (spec 0039 pivot, App\Enums\StatusGroup)
 * is ALSO left out here: it is already reachable via the basic `set` column
 * filter (PipelineStatusColumnCatalog), and no advanced-filter widget type
 * in this catalogue's repertoire (AdvancedFilterType) carries a static
 * options list end-to-end — every existing usage of Select/Enum in this
 * codebase is unused, only Relation (an id-based FK lookup) is — so adding a
 * second, narrower path here would be speculative (engineering.md §1.3).
 */
final class PipelineStatusAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'name',
                'label' => 'pipelineStatuses.advancedFilters.name',
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
                'label' => 'pipelineStatuses.advancedFilters.sortOrderRange',
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
                'label' => 'pipelineStatuses.advancedFilters.createdRange',
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
