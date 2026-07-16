<?php

namespace App\Tables\PipelineStatuses;

/**
 * Declarative column/filter/action catalogue for the `pipeline-statuses`
 * domain (spec 0023). Extracted out of PipelineStatusesTableDefinition
 * (file-size split, engineering.md §6): pure data (no logic), mirroring
 * SourceColumnCatalog. `name`/`color`/`sort_order`/`created_at` are real DB
 * columns handled entirely by the generic engine. `color` is deliberately
 * not sortable/filterable (a swatch value, not a meaningful ordering/filter
 * axis). `status_group` (spec 0039, D-6/D-7) is DERIVED (no real column,
 * PipelineStatusesTableDefinition delegates to StatusGroupColumn): neither
 * sortable nor basic-filterable — reachable only via the advanced Text
 * filter on the group's name.
 */
final class PipelineStatusColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'pipelineStatuses.columns.name',
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
                'label' => 'pipelineStatuses.columns.color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'sort_order',
                'label' => 'pipelineStatuses.columns.sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'status_group',
                'label' => 'pipelineStatuses.columns.statusGroup',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'created_at',
                'label' => 'pipelineStatuses.columns.created_at',
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
                'permission' => 'pipeline-statuses.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'pipeline-statuses.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'pipeline-statuses.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'pipeline-statuses.viewActivity',
            ],
        ];
    }
}
