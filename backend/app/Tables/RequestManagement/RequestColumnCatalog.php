<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

/**
 * Declarative column/filter/action catalogue for the `request-management`
 * domain (spec 0049): an OPERATIVE view over the same `opportunities` rows
 * (D-1, no new entity). The visible columns are the operator's worklist:
 *  - `product_categories` ("Categoria servizi di riferimento") — AGGREGATED
 *    to-many via `productLines.productCategory`, filterable (set) but never
 *    sortable (no single related row to order by).
 *  - `operator_ga2` ("Operatore") — the Account Manager at pivot position 2
 *    (GA2), display-only.
 *  - `workflow_status` ("Stato di lavorazione") — the related working-state
 *    row's name + color token for the badge, sortable + set-filterable.
 *  - `first_name`/`last_name`/`tax_code`/`phone` — the CLIENT's anagraphic
 *    fields, read from the Registry's PersonalData card (phone = its primary
 *    phone/mobile contact); display-only.
 * All derived/anagraphic values are resolved by
 * RequestManagementTableDefinition::mapRow() from eager-loaded relations. A
 * hidden `updated_at` column exists solely to back the default sort.
 */
final class RequestColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::aggregatedColumn('product_categories', 'requestManagement.columns.serviceCategory'),
            self::textColumn('operator_ga2', 'requestManagement.columns.operator'),
            self::derivedColumn('workflow_status', 'requestManagement.columns.workflowStatus'),
            self::textColumn('first_name', 'requestManagement.columns.firstName'),
            self::textColumn('last_name', 'requestManagement.columns.lastName'),
            self::textColumn('tax_code', 'requestManagement.columns.taxCode'),
            self::textColumn('phone', 'requestManagement.columns.phone'),
            [
                // Hidden: not shown, but a real sortable DB column so the
                // default "recently worked first" ordering (defaultSort)
                // resolves against a valid catalogue column — the generic
                // engine 422s an unknown sort colId.
                'id' => 'updated_at',
                'label' => 'requestManagement.columns.updatedAt',
                'type' => 'datetime',
                'visible' => false,
                'sortable' => true,
                'filterable' => false,
            ],
        ];
    }

    /**
     * A DERIVED (related-row-name) column declaration: filterable via the
     * `set` widget and sortable (a correlated subquery), mirroring
     * OpportunityColumnCatalog's own helper.
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
     * A to-many AGGREGATED (via `productLines`) column declaration:
     * filterable via `set` (whereHas on the related row's name) but never
     * sortable — no single related row to order by.
     *
     * @return array<string, mixed>
     */
    private static function aggregatedColumn(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * A display-only text column sourced from a related entity (the client's
     * anagraphic fields — nome/cognome/codice fiscale/telefono — and the GA2
     * operator name): neither sortable nor filterable. The value is computed
     * from the eager-loaded relations by RequestManagementTableDefinition::mapRow().
     *
     * @return array<string, mixed>
     */
    private static function textColumn(string $id, string $label): array
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
     * Only the filterable columns produce a filter descriptor (display-only
     * text columns carry no `filterType`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return array_values(array_map(
            static fn (array $column): array => [
                'columnId' => $column['id'],
                'type' => $column['filterType'],
            ],
            array_filter(
                self::columns(),
                static fn (array $column): bool => ($column['filterable'] ?? false) === true,
            ),
        ));
    }

    /**
     * Only `view` ("Lavora"): no edit/delete (the CRUD boundary stays on
     * `opportunities.*`, never request-management), no documents (out of
     * scope, spec 0049 scope/out). No `activity` row action either: the
     * generic activity-log framework resolves its Policy by MODEL CLASS
     * (Opportunity), so a `request-management`-gated activity surface would
     * have been misleading (see config/activity-log.php) — the module has no
     * separately-gated activity endpoint (lead decision).
     *
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
                'permission' => 'request-management.view',
            ],
        ];
    }
}
