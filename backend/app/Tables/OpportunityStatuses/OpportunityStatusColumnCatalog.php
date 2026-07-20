<?php

namespace App\Tables\OpportunityStatuses;

use App\Enums\StatusGroup;

/**
 * Declarative column/filter/action catalogue for the `opportunity-statuses`
 * domain (spec 0043). Extracted out of OpportunityStatusesTableDefinition
 * (file-size split, engineering.md §6): pure data (no logic). Every column
 * (name/color/sort_order/group/
 * created_at) is a real DB column handled entirely by the generic engine.
 * `color` is deliberately not sortable/filterable (a swatch value, not a
 * meaningful ordering/filter axis). `group` (App\Enums\StatusGroup) is a
 * `set` filter with a static options catalogue.
 */
final class OpportunityStatusColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'opportunityStatuses.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'color',
                'label' => 'opportunityStatuses.columns.color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'sort_order',
                'label' => 'opportunityStatuses.columns.sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'group',
                'label' => 'opportunityStatuses.columns.group',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => StatusGroup::values(),
            ],
            [
                'id' => 'created_at',
                'label' => 'opportunityStatuses.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'sort_order', 'type' => 'number'],
            ['columnId' => 'group', 'type' => 'set', 'options' => StatusGroup::values()],
            ['columnId' => 'created_at', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            [
                'key' => 'view',
                'label' => 'actions.view',
                'icon' => 'eye',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'opportunity-statuses.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'opportunity-statuses.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'opportunity-statuses.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'opportunity-statuses.viewActivity',
            ],
        ];
    }
}
