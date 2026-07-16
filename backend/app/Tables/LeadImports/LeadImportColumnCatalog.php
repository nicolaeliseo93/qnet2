<?php

namespace App\Tables\LeadImports;

use App\Enums\ImportStatus;

/**
 * Declarative column/filter/action catalogue for the `lead-imports` domain:
 * the read-only history of the actor's own lead import runs, rendered by the
 * generic table engine instead of a bespoke HTML table. Pure data (no logic),
 * mirroring LeadStatusColumnCatalog. Every column is a real `import_runs`
 * column; `error_rows` is the contract alias for the `invalid_rows` column
 * (see ImportRunResource) and is mapped in LeadImportsTableDefinition::mapRow.
 */
final class LeadImportColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'created_at',
                'label' => 'leadImports.columns.date',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'original_filename',
                'label' => 'leadImports.columns.file',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'total_rows',
                'label' => 'leadImports.columns.records',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'imported_rows',
                'label' => 'leadImports.columns.imported',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                // Real `invalid_rows` column (the wizard's error count), labelled
                // "Errors" for the UI. Kept as the real column id — not an alias —
                // so the generic ORDER BY/WHERE whitelist targets a real column
                // with no derived-sort/filter hook.
                'id' => 'invalid_rows',
                'label' => 'leadImports.columns.errors',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'status',
                'label' => 'leadImports.columns.status',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => array_map(
                    static fn (ImportStatus $case): string => $case->value,
                    ImportStatus::cases(),
                ),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'original_filename', 'type' => 'text'],
            ['columnId' => 'total_rows', 'type' => 'number'],
            ['columnId' => 'imported_rows', 'type' => 'number'],
            ['columnId' => 'invalid_rows', 'type' => 'number'],
            [
                'columnId' => 'status',
                'type' => 'set',
                'options' => array_map(
                    static fn (ImportStatus $case): string => $case->value,
                    ImportStatus::cases(),
                ),
            ],
        ];
    }

    /**
     * Row actions, mirroring the CRUD tables' flow: `view` reopens the run in
     * the import wizard (the frontend adapter navigates to
     * `/leads/import?runId={id}`), `delete` removes the run through the generic
     * bulk-delete engine. Gated by the `import-runs.*` module permissions
     * (spec 0034) rather than the domain's `leads.import` ability; `delete` is
     * additionally per-row gated by ImportRunPolicy (ownership) in
     * actionsFor().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            [
                'key' => 'view',
                'label' => 'leadImports.actions.view',
                'icon' => 'eye',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'import-runs.view',
            ],
            [
                'key' => 'delete',
                'label' => 'leadImports.actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'import-runs.delete',
            ],
        ];
    }
}
