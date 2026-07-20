<?php

namespace App\Tables\Leads;

use App\Enums\LeadLifecycleStatus;

/**
 * Declarative column/filter/action catalogue for the `leads` domain (spec
 * 0024). Extracted out of LeadsTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic).
 *
 * `created_at` is a real DB column handled entirely by the generic engine.
 * `registry`/`campaign`/`source`/`operator` are STANDARD
 * derived (related-row name) columns, resolved by LeadsTableDefinition (spec
 * 0041 D-1: the contact is now an Anagrafica, not a Referent).
 * `lead_status` is a display-only lifecycle badge derived from operator and
 * opportunity state.
 * `operational_site` is the ONE specially-derived column (BR-3: no own name,
 * sort/filter pass through the site's primary address `line1`), delegated to
 * LeadOperationalSiteColumn.
 */
final class LeadColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::derivedColumn('registry', 'leads.columns.registry'),
            self::derivedColumn('campaign', 'leads.columns.campaign'),
            self::derivedColumn('operational_site', 'leads.columns.operationalSite'),
            self::derivedColumn('source', 'leads.columns.source'),
            self::derivedColumn('operator', 'leads.columns.operator'),
            [
                'id' => 'lead_status',
                'label' => 'leads.columns.leadStatus',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => self::leadStatusValues(),
            ],
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
     * @return array<int, string>
     */
    private static function leadStatusValues(): array
    {
        return array_map(
            static fn (LeadLifecycleStatus $status): string => $status->value,
            LeadLifecycleStatus::cases(),
        );
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
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'leads.viewActivity',
            ],
            [
                // Deferred conversion (spec 0044): gated on the ABILITY to
                // create the target resource (opportunities.create), not on
                // any leads.* permission — the per-row whitelist
                // (LeadsTableDefinition::actionsFor) additionally hides it
                // once the lead is already converted.
                'key' => 'convert_to_opportunity',
                'label' => 'actions.convertToOpportunity',
                'icon' => 'arrow-right-left',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'opportunities.create',
            ],
        ];
    }
}
