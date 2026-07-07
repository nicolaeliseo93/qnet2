<?php

namespace App\Tables\EaSectors;

/**
 * Declarative column/filter/action catalogue for the `ea-sectors` domain
 * (spec 0018). Extracted out of EaSectorsTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic), mirroring
 * ProductCategoryColumnCatalog.
 *
 * `name`/`created_at` are real DB columns handled entirely by the generic
 * engine. `parent` has no real DB column of its own (it is the related
 * parent sector's name) and is DERIVED, mirroring
 * ProductCategoriesTableDefinition's `parent` column.
 */
final class EaSectorColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'eaSectors.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                // The parent sector's name, derived from the self-referencing
                // parent() relation. Sorted via a correlated subquery, filtered
                // via whereHas (both in the definition). Root sectors (no
                // parent) surface as null/empty.
                'id' => 'parent',
                'label' => 'eaSectors.columns.parent',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'created_at',
                'label' => 'eaSectors.columns.created_at',
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
            ['columnId' => 'parent', 'type' => 'set'],
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
                'permission' => 'ea-sectors.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'ea-sectors.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'ea-sectors.delete',
            ],
        ];
    }
}
