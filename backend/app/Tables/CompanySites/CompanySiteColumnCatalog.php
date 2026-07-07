<?php

namespace App\Tables\CompanySites;

/**
 * Declarative column/filter/action catalogue for the `company-sites` domain
 * (spec 0020).
 *
 * Extracted out of CompanySitesTableDefinition (file-size split, engineering.md
 * §6): pure data (no logic), mirroring CompanyColumnCatalog. The 3 geo/postal
 * columns (city/province/region/postal_code) have no real DB column of their
 * own — derived from the site's primary address, handled by
 * CompanySiteAddressColumns.
 */
final class CompanySiteColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'id',
                'label' => 'companySites.columns.id',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                // Exclusive site-level default flag (spec 0020), toggled via
                // POST /company-sites/{id}/set-default. Real column.
                'id' => 'is_default',
                'label' => 'companySites.columns.isDefault',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'name',
                'label' => 'companySites.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'email',
                'label' => 'companySites.columns.email',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'vat_number',
                'label' => 'companySites.columns.vatNumber',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'phone',
                'label' => 'companySites.columns.phone',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                // Geo name from the primary address. Set filter with
                // backend-resolved options (distinct names in use). Sorted via
                // a correlated subquery.
                'id' => 'city',
                'label' => 'companySites.columns.city',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'province',
                'label' => 'companySites.columns.province',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'region',
                'label' => 'companySites.columns.region',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Free-text postal code of the primary address. COMPUTED (no
                // real DB column), conditions-only (spec 0005): no clean
                // discrete list worth an Excel-like Set Filter.
                'id' => 'postal_code',
                'label' => 'companySites.columns.postalCode',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'created_at',
                'label' => 'companySites.columns.createdAt',
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
            ['columnId' => 'is_default', 'type' => 'set'],
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'email', 'type' => 'text'],
            ['columnId' => 'vat_number', 'type' => 'text'],
            ['columnId' => 'phone', 'type' => 'text'],
            // Geo set filters: options resolved dynamically in optionsFor().
            ['columnId' => 'city', 'type' => 'set'],
            ['columnId' => 'province', 'type' => 'set'],
            ['columnId' => 'region', 'type' => 'set'],
            ['columnId' => 'postal_code', 'type' => 'text'],
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
                'permission' => 'company-sites.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'company-sites.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'company-sites.delete',
            ],
        ];
    }
}
