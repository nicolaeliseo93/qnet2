<?php

namespace App\Imports\Leads;

/**
 * Static catalogue backing `LeadsImportDefinition::columns()/fields()/
 * globalConfig()` (spec 0033): the mappable Registry+Lead field ids and the
 * configuration-step global fields, kept off the main definition class to
 * stay under the 300-line soft limit (engineering.md §6).
 *
 * `label` values are i18n KEYS (`imports.leads.fields.*`/`imports.leads.
 * global.*`), not literal display strings — the frontend's dedicated
 * `features/imports` i18n bundle resolves them.
 */
final class LeadImportFieldCatalog
{
    /**
     * Mappable field catalogue: id => [group, type]. Field ids double as the
     * `resolved`/`mapped_values` keys LeadProfileBuilder/LeadDuplicateMatcher
     * read, and MUST match what NameSplitRecognizer (`first_name`/
     * `last_name`)/GeoRecognizer (`*_id`) produce.
     *
     * @var array<string, array{group: string, type: string}>
     */
    private const array FIELD_CATALOG = [
        'full_name' => ['group' => 'identity', 'type' => 'text'],
        'first_name' => ['group' => 'identity', 'type' => 'text'],
        'last_name' => ['group' => 'identity', 'type' => 'text'],
        'company_name' => ['group' => 'identity', 'type' => 'text'],
        'tax_code' => ['group' => 'identity', 'type' => 'text'],
        'vat_number' => ['group' => 'identity', 'type' => 'text'],
        'email' => ['group' => 'contact', 'type' => 'text'],
        'phone' => ['group' => 'contact', 'type' => 'text'],
        'mobile' => ['group' => 'contact', 'type' => 'text'],
        'street' => ['group' => 'address', 'type' => 'text'],
        'postal_code' => ['group' => 'address', 'type' => 'text'],
        'country' => ['group' => 'address', 'type' => 'text'],
        'region' => ['group' => 'address', 'type' => 'text'],
        'province' => ['group' => 'address', 'type' => 'text'],
        'city' => ['group' => 'address', 'type' => 'text'],
        'notes' => ['group' => 'lead', 'type' => 'textarea'],
    ];

    /**
     * Configuration-step global fields, applied to every imported row.
     * `project_id` narrows the campaign choice client-side only — a Lead has
     * no `project_id` column of its own (it inherits one via `campaign_id`).
     *
     * @var array<int, array{id: string, required: bool, for_select_resource: string}>
     */
    private const array GLOBAL_FIELDS = [
        ['id' => 'campaign_id', 'required' => true, 'for_select_resource' => 'campaigns'],
        ['id' => 'project_id', 'required' => false, 'for_select_resource' => 'projects'],
        ['id' => 'source_id', 'required' => false, 'for_select_resource' => 'sources'],
        ['id' => 'operator_id', 'required' => false, 'for_select_resource' => 'users'],
    ];

    /**
     * Fields StagedRowBuilder defaults to `config('imports.placeholder')`
     * when still blank after recognizers ran (spec 0033 delta
     * D-2026-07-15-placeholder-review-fields): a Registry's identity card
     * needs a first/last name — NameSplitRecognizer supplies these from
     * `full_name` when possible, the placeholder covers what it cannot.
     *
     * @var array<int, string>
     */
    private const array REQUIRED_FOR_CREATION = ['first_name', 'last_name'];

    /**
     * @return array<int, array{id: string, required: bool}>
     */
    public function columns(): array
    {
        // No single column is unconditionally required — a row's identity is
        // an OR of name/company/contact, enforced by LeadRowValidator.
        return array_map(
            static fn (string $id): array => ['id' => $id, 'required' => false],
            array_keys(self::FIELD_CATALOG),
        );
    }

    /**
     * @return array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>
     */
    public function fields(): array
    {
        return array_map(
            static fn (string $id, array $meta): array => [
                'id' => $id,
                'label' => "imports.leads.fields.{$id}",
                'required' => false,
                'group' => $meta['group'],
                'type' => $meta['type'],
            ],
            array_keys(self::FIELD_CATALOG),
            self::FIELD_CATALOG,
        );
    }

    /**
     * @return array<int, array{id: string, label: string, required: bool, for_select_resource: ?string, default: mixed}>
     */
    public function globalConfig(): array
    {
        return array_map(
            fn (array $field): array => [
                'id' => $field['id'],
                'label' => "imports.leads.global.{$field['id']}",
                'required' => $field['required'],
                'for_select_resource' => $field['for_select_resource'],
                'default' => null,
            ],
            self::GLOBAL_FIELDS,
        );
    }

    /**
     * @return array<int, string>
     */
    public function requiredForCreation(): array
    {
        return self::REQUIRED_FOR_CREATION;
    }

    /**
     * The review grid's FINAL persisted fields: every mappable field EXCEPT
     * `full_name` — an input-only column, replaced by NameSplitRecognizer's
     * `first_name`/`last_name` output (+ the placeholder), never itself
     * persisted.
     *
     * @return array<int, array{id: string, label: string}>
     */
    public function reviewFields(): array
    {
        return array_values(array_filter(
            array_map(
                static fn (array $field): array => ['id' => $field['id'], 'label' => $field['label']],
                $this->fields(),
            ),
            static fn (array $field): bool => $field['id'] !== 'full_name',
        ));
    }
}
