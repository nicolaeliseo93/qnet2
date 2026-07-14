<?php

namespace App\Tables\Projects;

/**
 * Declarative column/filter/action catalogue for the `projects` domain (spec
 * 0023). Extracted out of ProjectsTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic).
 *
 * `code`/`name`/`start_date`/`end_date`/`total_budget`/`target_lead`/
 * `created_at` are real DB columns handled entirely by the generic engine.
 * `registry`/`project_status`/`source`/`business_function`/`country`/
 * `state`/`province`/`city`/`product_category`/`partner` have no real column
 * of their own (each is the related row's name) and are DERIVED, resolved by
 * ProjectsTableDefinition — only `registry`/`project_status` are sortable (a
 * correlated subquery per spec 0023 table_definitions); the rest are
 * filterable-only. `geo_scope` (spec 0027, D-2) is a purely COMPUTED,
 * DISPLAY-ONLY column: neither sortable nor filterable (no real value to
 * join/sort on), mirroring ProjectStatusColumnCatalog's `color`.
 */
final class ProjectColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'code',
                'label' => 'projects.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'name',
                'label' => 'projects.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            self::derivedColumn('registry', 'projects.columns.registry', sortable: true),
            self::derivedColumn('project_status', 'projects.columns.project_status', sortable: true),
            self::derivedColumn('source', 'projects.columns.source'),
            self::derivedColumn('business_function', 'projects.columns.business_function'),
            self::derivedColumn('country', 'projects.columns.country'),
            self::derivedColumn('state', 'projects.columns.state'),
            self::derivedColumn('province', 'projects.columns.province'),
            self::derivedColumn('city', 'projects.columns.city'),
            [
                'id' => 'geo_scope',
                'label' => 'projects.columns.geo_scope',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            self::derivedColumn('product_category', 'projects.columns.product_category'),
            self::derivedColumn('partner', 'projects.columns.partner'),
            [
                'id' => 'start_date',
                'label' => 'projects.columns.start_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'end_date',
                'label' => 'projects.columns.end_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'total_budget',
                'label' => 'projects.columns.total_budget',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'target_lead',
                'label' => 'projects.columns.target_lead',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'created_at',
                'label' => 'projects.columns.created_at',
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
     * `registry`/`project_status`).
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
     * Only FILTERABLE columns get a filter declaration — `geo_scope`
     * (display-only, spec 0027) has no `filterType` to declare.
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
                'permission' => 'projects.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'projects.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'projects.delete',
            ],
        ];
    }
}
