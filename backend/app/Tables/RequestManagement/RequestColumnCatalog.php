<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

/**
 * Declarative column/filter/action catalogue for the `request-management`
 * domain (spec 0049): an OPERATIVE view over the same `opportunities` rows
 * (D-1, no new entity). The visible columns are the operator's worklist:
 *  - `product_categories` ("Categoria prodotto") — AGGREGATED
 *    to-many via `productLines.productCategory`, filterable (set) but never
 *    sortable (no single related row to order by).
 *  - `operator_ga2` ("Operatore") — the Account Manager at pivot position 2
 *    (GA2), display-only.
 *  - `workflow_status` ("Stato di lavorazione") — the related working-state
 *    row's name + color token for the badge, sortable + set-filterable.
 *  - `first_name`/`last_name`/`tax_code`/`phone` — the CLIENT's anagraphic
 *    fields, read from the Registry's PersonalData card (phone = its primary
 *    phone/mobile contact); display-only.
 *  - `next_callback_at` ("Prossimo richiamo", spec 0052 D-1/D-5) — a real
 *    `opportunities` column, sortable + date-filterable via the generic
 *    engine, mirroring `OpportunityColumnCatalog`'s `created_at`.
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
            self::aggregatedColumn('product_categories', 'requestManagement.columns.productCategory'),
            self::textColumn('operator_ga2', 'requestManagement.columns.operator'),
            self::derivedColumn('workflow_status', 'requestManagement.columns.workflowStatus'),
            self::textColumn('first_name', 'requestManagement.columns.firstName'),
            self::textColumn('last_name', 'requestManagement.columns.lastName'),
            self::textColumn('tax_code', 'requestManagement.columns.taxCode'),
            self::textColumn('phone', 'requestManagement.columns.phone'),
            [
                // Real DB column (spec 0052 D-1/D-5): the operator's planned
                // next contact, sortable/filterable via the generic engine
                // like OpportunityColumnCatalog's `created_at`.
                'id' => 'next_callback_at',
                'label' => 'requestManagement.columns.nextCallbackAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
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
     * `view` ("Lavora") and `documents` — no edit/delete (the CRUD boundary
     * stays on `opportunities.*`, never request-management). `documents`
     * reuses the polymorphic Attachment subsystem on the same Opportunity
     * record as the opportunities module, but is gated by this module's OWN
     * permission (`request-management.viewDocuments`, D-2) and carries the
     * per-row `documents_count` badge. `activity` (D-7, amended) opens this
     * module's OWN activity surface: the generic framework used to resolve its
     * Policy by MODEL CLASS (Opportunity), which is why the action did not
     * exist — it now goes through RequestManagementActivityAuthorizer, gated by
     * `request-management.viewActivity`. Declared LAST on purpose: with the
     * shared `INLINE_ACTION_LIMIT`, the fourth action falls into the overflow
     * (three-dots) menu, which is where consultation belongs.
     * `notes` (spec 0052 B4b) opens the collaborative-notes dialog: gated by
     * `request-management.view`, NOT a notes permission — reading a record's
     * notes is inherited from the ability to open the record (D-6), while
     * writing is separately authorized server-side by `notes.create` inside
     * the dialog itself. `count_field` (spec 0052 B4c, reversing the earlier
     * "out of scope" call) carries `notes_count` — every note on the record,
     * roots AND replies, soft-deleted excluded — mirroring `documents`'
     * `documents_count` badge.
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
            [
                'key' => 'documents',
                'label' => 'actions.documents',
                'icon' => 'paperclip',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.viewDocuments',
                'count_field' => 'documents_count',
            ],
            [
                'key' => 'notes',
                'label' => 'actions.notes',
                'icon' => 'message-square',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.view',
                'count_field' => 'notes_count',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.viewActivity',
            ],
        ];
    }
}
