<?php

namespace App\Tables\Campaigns;

/**
 * Declarative column/filter/action catalogue for the `campaigns` domain
 * (spec 0023). Extracted out of CampaignsTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic).
 *
 * `code`/`name`/`start_date`/`end_date`/`total_budget`/`target_lead`/
 * `created_at` are real DB columns handled entirely by the generic engine.
 * `project`/`pipeline_status`/`source` have no real column of
 * their own and are DERIVED, resolved by CampaignsTableDefinition — only
 * `project` is sortable (a correlated subquery); `pipeline_status`/
 * `source` are filterable-only (spec 0023 table_definitions).
 *
 * `country`/`state`/`province`/`city`/`geo_scope` (spec 0027, BR-5/D-2) are
 * DISPLAY-ONLY: neither sortable nor filterable. The MERGED (campaign-or-
 * project) geo value is not a plain join like the other derived columns —
 * the same pragmatic choice already made for `pipeline_status` (AC-032),
 * just without that column's set-filter/sort support.
 */
final class CampaignColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'code',
                'label' => 'campaigns.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            self::derivedColumn('project', 'campaigns.columns.project', sortable: true),
            [
                'id' => 'name',
                'label' => 'campaigns.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            self::derivedColumn('pipeline_status', 'campaigns.columns.pipeline_status'),
            self::derivedColumn('source', 'campaigns.columns.source'),
            self::displayOnlyColumn('country', 'campaigns.columns.country'),
            self::displayOnlyColumn('state', 'campaigns.columns.state'),
            self::displayOnlyColumn('province', 'campaigns.columns.province'),
            self::displayOnlyColumn('city', 'campaigns.columns.city'),
            self::displayOnlyColumn('geo_scope', 'campaigns.columns.geo_scope'),
            [
                'id' => 'start_date',
                'label' => 'campaigns.columns.start_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'end_date',
                'label' => 'campaigns.columns.end_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'total_budget',
                'label' => 'campaigns.columns.total_budget',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'target_lead',
                'label' => 'campaigns.columns.target_lead',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'created_at',
                'label' => 'campaigns.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * A DERIVED (related-row-name) column declaration: filterable via the
     * `set` widget, only sortable when explicitly requested (spec 0023: just
     * `project`).
     *
     * @return array<string, mixed>
     */
    private static function derivedColumn(string $id, string $label, bool $sortable = false): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => $sortable,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * A COMPUTED, DISPLAY-ONLY column (spec 0027): the merged geo values and
     * `geo_scope` have no real column/relation of their own to sort or
     * filter on, mirroring PipelineStatusColumnCatalog's `color`.
     *
     * @return array<string, mixed>
     */
    private static function displayOnlyColumn(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => false,
        ];
    }

    /**
     * Only FILTERABLE columns get a filter declaration — the display-only
     * geo columns (spec 0027) have no `filterType` to declare.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        $filterable = array_filter(
            self::columns(),
            static fn (array $column): bool => ($column['filterable'] ?? false) === true,
        );

        return array_values(array_map(
            static fn (array $column): array => [
                'columnId' => $column['id'],
                'type' => $column['filterType'],
            ],
            $filterable,
        ));
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
                'permission' => 'campaigns.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'campaigns.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'campaigns.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'campaigns.viewActivity',
            ],
        ];
    }
}
