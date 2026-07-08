<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\Types\Concerns\AppliesSetFilter;
use App\CustomFields\Types\Concerns\DerivesRequiredRule;
use App\CustomFields\Types\Concerns\OrdersByJsonPath;
use App\CustomFields\Types\Concerns\ResolvesDistinctJsonValues;
use App\CustomFields\Types\Concerns\ResolvesJsonColumn;
use App\Models\CustomFieldDefinition;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Reference to another custom-fieldable entity (spec 0021 MVP).
 * `relation_target`: {entity_type, cardinality: one|many, for_select_resource}.
 *
 * Target resolution is delegated to App\CustomFields\CustomFieldEntityRegistry
 * (`modelClassFor()`), NOT re-derived here: `relation_target.entity_type` is,
 * by construction, always a custom-fieldable domain (both TableRegistry AND
 * Authorization registered) — the admin CRUD (spec 0021 ADMIN CRUD item)
 * validates that at definition-save time.
 */
class RelationFieldType implements FieldTypeHandler
{
    use AppliesSetFilter;
    use DerivesRequiredRule;
    use OrdersByJsonPath;
    use ResolvesDistinctJsonValues;
    use ResolvesJsonColumn;

    public function __construct(private readonly CustomFieldEntityRegistry $entityRegistry) {}

    public function key(): string
    {
        return 'relation';
    }

    public function storageType(): string
    {
        return 'json';
    }

    public function columnType(): string
    {
        return 'text';
    }

    public function filterType(): string
    {
        return 'set';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        $table = $this->targetTable($definition);
        $base = $this->requiredOrNullable($definition);

        if ($this->isMany($definition)) {
            $rules = [$base, 'array'];

            if ($table !== null) {
                $rules[] = $this->allExistRule($table);
            }

            return $rules;
        }

        $rules = [$base, 'integer'];

        if ($table !== null) {
            $rules[] = Rule::exists($table, 'id');
        }

        return $rules;
    }

    public function normalizeForStore(mixed $value, CustomFieldDefinition $definition): mixed
    {
        if ($value === null) {
            return null;
        }

        return $this->isMany($definition)
            ? array_values(array_map(static fn (mixed $id): int => (int) $id, (array) $value))
            : (int) $value;
    }

    public function resolveForRead(mixed $stored, CustomFieldDefinition $definition): mixed
    {
        return $stored;
    }

    public function toMeta(CustomFieldDefinition $definition): array
    {
        $target = $definition->relation_target ?? [];

        return [
            'type' => $this->key(),
            'config' => $definition->config ?? [],
            'relation' => [
                'for_select_resource' => $target['for_select_resource'] ?? null,
                'cardinality' => $target['cardinality'] ?? 'one',
            ],
        ];
    }

    private function isMany(CustomFieldDefinition $definition): bool
    {
        return ($definition->relation_target['cardinality'] ?? 'one') === 'many';
    }

    /**
     * The target model's table, or null when `relation_target.entity_type`
     * does not resolve to a registered custom-fieldable domain.
     */
    private function targetTable(CustomFieldDefinition $definition): ?string
    {
        $entityType = $definition->relation_target['entity_type'] ?? null;

        if (! is_string($entityType) || $entityType === '') {
            return null;
        }

        $modelClass = $this->entityRegistry->modelClassFor($entityType);

        return $modelClass === null ? null : (new $modelClass)->getTable();
    }

    /**
     * Bulk "every id exists" check for the `many` cardinality — one query for
     * the whole array rather than N `exists:` lookups (Rule::forEach is
     * avoided here entirely, see Concerns\ValidatesEachElement's docblock).
     */
    private function allExistRule(string $table): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($table): void {
            $ids = array_values(array_unique(array_filter(
                (array) $value,
                static fn (mixed $id): bool => is_scalar($id),
            )));

            if ($ids === []) {
                return;
            }

            $found = DB::table($table)->whereIn('id', $ids)->count();

            if ($found !== count($ids)) {
                $fail("The {$attribute} contains an id that does not exist.");
            }
        };
    }
}
