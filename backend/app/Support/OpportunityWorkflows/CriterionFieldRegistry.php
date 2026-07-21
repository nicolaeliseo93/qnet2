<?php

declare(strict_types=1);

namespace App\Support\OpportunityWorkflows;

use App\Models\Opportunity;
use InvalidArgumentException;

/**
 * Centralized allow-list of the "criterion fields" a workflow may be matched
 * on (spec 0047, scope item: "Allow-list dei campi-criterio centralizzata
 * backend"). The single source of truth consumed by both the resolver
 * (App\Services\OpportunityWorkflows\OpportunityWorkflowResolver, a later
 * lane) and the FormRequest that validates OpportunityWorkflowCriterion
 * payloads — never an ad-hoc list duplicated at either call site (anti-SQLi:
 * `field`/`existsTable()` values only ever come from THIS registry, never
 * from raw request input).
 *
 * `state_id`/`source_id` are direct Opportunity columns; `business_function_id`/
 * `product_category_id` match against ANY row of the opportunity's
 * `productLines()` collection (spec 0040 amendment rev.3), not a column on
 * `opportunities` itself.
 */
final class CriterionFieldRegistry
{
    /**
     * @var array<string, array{for_select_resource: string, table: string, multi_valued: bool, from_product_lines: bool}>
     */
    private const array FIELDS = [
        'state_id' => [
            'for_select_resource' => 'states',
            'table' => 'states',
            'multi_valued' => false,
            'from_product_lines' => false,
        ],
        'source_id' => [
            'for_select_resource' => 'sources',
            'table' => 'sources',
            'multi_valued' => false,
            'from_product_lines' => false,
        ],
        'business_function_id' => [
            'for_select_resource' => 'business-functions',
            'table' => 'business_functions',
            'multi_valued' => true,
            'from_product_lines' => true,
        ],
        'product_category_id' => [
            'for_select_resource' => 'product-categories',
            'table' => 'product_categories',
            'multi_valued' => true,
            'from_product_lines' => true,
        ],
    ];

    /**
     * The allow-list, shaped for GET /api/opportunity-workflows/criterion-fields
     * (AC-022): field, i18n label key, for-select resource, multi_valued.
     *
     * @return array<int, array{field: string, label: string, for_select_resource: string, multi_valued: bool}>
     */
    public static function allowedFields(): array
    {
        return array_map(
            static fn (string $field, array $definition): array => [
                'field' => $field,
                'label' => "opportunityWorkflows.criterionFields.{$field}",
                'for_select_resource' => $definition['for_select_resource'],
                'multi_valued' => $definition['multi_valued'],
            ],
            array_keys(self::FIELDS),
            self::FIELDS,
        );
    }

    public static function isAllowed(string $field): bool
    {
        return array_key_exists($field, self::FIELDS);
    }

    /**
     * The DB table backing `value_id` for $field, for a `Rule::exists()`
     * check — never accepts raw input, only an already-allow-listed $field.
     */
    public static function existsTable(string $field): string
    {
        if (! self::isAllowed($field)) {
            throw new InvalidArgumentException("Unknown criterion field [{$field}].");
        }

        return self::FIELDS[$field]['table'];
    }

    /**
     * The distinct value(s) $opportunity actually carries for $field
     * (AC-013): a direct column read for `state_id`/`source_id` (empty when
     * null), or the distinct, non-null values across every `productLines()`
     * row for `business_function_id`/`product_category_id`. Assumes
     * `productLines` is already eager-loaded by the caller (no N+1 query
     * here).
     *
     * @return array<int, int>
     */
    public static function opportunityValues(Opportunity $opportunity, string $field): array
    {
        if (! self::isAllowed($field)) {
            throw new InvalidArgumentException("Unknown criterion field [{$field}].");
        }

        if (! self::FIELDS[$field]['from_product_lines']) {
            $value = $opportunity->getAttribute($field);

            return $value === null ? [] : [(int) $value];
        }

        return $opportunity->productLines
            ->pluck($field)
            ->filter()
            ->unique()
            ->values()
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();
    }
}
