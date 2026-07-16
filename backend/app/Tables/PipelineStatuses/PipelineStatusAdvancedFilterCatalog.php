<?php

namespace App\Tables\PipelineStatuses;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `pipeline-statuses` domain (spec 0032). A
 * small lookup table (PipelineStatusColumnCatalog: name/color/sort_order/
 * status_group/created_at) — every real-column entry here is a direct-column
 * filter, handled entirely by the generic default (no domain override
 * needed). `color` is deliberately left out, mirroring the column
 * catalogue's own choice to keep it neither sortable nor filterable (a
 * swatch value, not a meaningful filter axis). `status_group` (spec 0039,
 * D-6/D-7) is the ONLY reachable filter path for the derived column of the
 * same name: a Text match on the group's name, handled by
 * PipelineStatusesTableDefinition::applyAdvancedFilter (delegated to
 * StatusGroupColumn) — `target` is unused for it (the override never falls
 * through to the generic default) but set for documentation symmetry.
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
                'name' => 'status_group',
                'label' => 'pipelineStatuses.advancedFilters.statusGroup',
                'type' => AdvancedFilterType::Text,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'status_group',
            ],
            [
                'name' => 'created_range',
                'label' => 'pipelineStatuses.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
        ];
    }
}
