<?php

namespace App\Tables\Leads;

/**
 * Declarative column/filter/action catalogue for the `leads` domain (spec
 * 0024). Extracted out of LeadsTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic).
 *
 * `created_at` is a real DB column handled entirely by the generic engine.
 * `referent`/`campaign`/`source`/`operator` are STANDARD derived (related-row
 * name) columns, resolved by LeadsTableDefinition. `operational_site` is the
 * ONE specially-derived column (BR-3: no own name, sort/filter pass through
 * the site's primary address `line1`), delegated to LeadOperationalSiteColumn.
 */
final class LeadColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::derivedColumn('referent', 'leads.columns.referent'),
            self::derivedColumn('campaign', 'leads.columns.campaign'),
            self::derivedColumn('operational_site', 'leads.columns.operationalSite'),
            self::derivedColumn('source', 'leads.columns.source'),
            self::derivedColumn('operator', 'leads.columns.operator'),
            [
                'id' => 'created_at',
                'label' => 'leads.columns.createdAt',
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
     * `set` widget and sortable (every one of the 5 relational columns here
     * has a correlated-subquery sort, unlike Campaigns' asymmetric choice).
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
                'permission' => 'leads.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'leads.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'leads.delete',
            ],
        ];
    }
}
