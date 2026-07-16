<?php

namespace App\Tables\Opportunities;

/**
 * Declarative column/filter/action catalogue for the `opportunities` domain
 * (spec 0040). Extracted out of OpportunitiesTableDefinition (file-size
 * split, engineering.md §6): pure data (no logic).
 *
 * `name`/`estimated_value`/`success_probability`/`start_date`/
 * `expected_close_date`/`created_at` are real DB columns handled entirely by
 * the generic engine. `registry`/`referent`/`commercial`/`supervisor`/
 * `source`/`product_category` are STANDARD relation-name derived columns
 * (own FK on the opportunity), resolved by OpportunitiesTableDefinition —
 * all 6 sortable (a correlated subquery), mirroring LeadColumnCatalog.
 */
final class OpportunityColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'opportunities.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            self::derivedColumn('registry', 'opportunities.columns.registry'),
            self::derivedColumn('referent', 'opportunities.columns.referent'),
            self::derivedColumn('commercial', 'opportunities.columns.commercial'),
            self::derivedColumn('supervisor', 'opportunities.columns.supervisor'),
            self::derivedColumn('source', 'opportunities.columns.source'),
            self::derivedColumn('product_category', 'opportunities.columns.productCategory'),
            [
                'id' => 'estimated_value',
                'label' => 'opportunities.columns.estimatedValue',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'success_probability',
                'label' => 'opportunities.columns.successProbability',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'start_date',
                'label' => 'opportunities.columns.startDate',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'expected_close_date',
                'label' => 'opportunities.columns.expectedCloseDate',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'created_at',
                'label' => 'opportunities.columns.createdAt',
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
     * `set` widget and sortable (every one of the 6 relational columns here
     * has a correlated-subquery sort), mirroring LeadColumnCatalog.
     *
     * @return array<string, mixed>
     */
    private static function derivedColumn(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return array_map(
            static fn (array $column): array => [
                'columnId' => $column['id'],
                'type' => $column['filterType'],
            ],
            self::columns(),
        );
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
                'permission' => 'opportunities.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'opportunities.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'opportunities.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'opportunities.viewActivity',
            ],
        ];
    }
}
