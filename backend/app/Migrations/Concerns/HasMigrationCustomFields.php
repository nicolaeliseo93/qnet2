<?php

namespace App\Migrations\Concerns;

use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldWriter;
use App\CustomFields\FieldTypeRegistry;
use App\Migrations\AbstractMigrationSource;
use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The generic custom-field seam every MigrationSource gets for free (spec
 * 0021 auto-persistence): the entity_type's active custom fields are
 * appended to `columns()`, surfaced in `mapRow()`'s preview cells, and
 * written onto the imported row by `persistCustomFields()` — a concrete
 * source never declares or wires any of this itself. Split out of
 * AbstractMigrationSource to keep it within the file-size budget
 * (engineering.md §6), mirroring App\Tables\CustomFields\CustomFieldColumnBuilder
 * being split out of CustomFieldAwareTableDefinition for the same reason.
 *
 * A source's `key()` IS its custom-field entity_type (spec 0021 convention,
 * e.g. "companies", "company-sites") — no separate mapping is needed.
 *
 * @phpstan-require-extends AbstractMigrationSource
 */
trait HasMigrationCustomFields
{
    /**
     * Maps a custom field handler's FieldTypeHandler::columnType()
     * (text|number|boolean|enum) to the migration preview column type union
     * (string|number|boolean|date, MigrationColumn) — an enum column
     * surfaces as its raw string value, never its option list. An unmapped
     * columnType (defensive; every registered handler returns one of the
     * four above) safely defaults to 'string'.
     */
    private const array CUSTOM_COLUMN_TYPE_MAP = [
        'text' => 'string',
        'number' => 'number',
        'boolean' => 'boolean',
        'enum' => 'string',
    ];

    /**
     * This entity_type's active custom-field columns (spec 0021), appended
     * after every native column by `columns()`. Empty when none are defined
     * — the common case for a source before its first custom field exists.
     *
     * The column `id` is the definition's raw key (e.g. "store_id"), NOT the
     * internal `custom.` namespace: the migration contract mirrors the raw
     * field names the external legacy source actually sends, and is the exact
     * key `persistCustomFields()` reads back off the record.
     *
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function customColumns(): array
    {
        $types = app(FieldTypeRegistry::class);

        return $this->customFieldDefinitions()
            ->map(fn (CustomFieldDefinition $definition): array => [
                'id' => $definition->key,
                'label' => $definition->label,
                'type' => self::CUSTOM_COLUMN_TYPE_MAP[$types->resolve($definition->type)->columnType()] ?? 'string',
            ])
            ->all();
    }

    /**
     * This entity_type's active custom-field preview cells, read straight
     * off the raw external record by the definition's own key — the same raw
     * key the column `id` exposes and `persistCustomFields()` writes from.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function mapCustomRow(array $record): array
    {
        $row = [];

        foreach ($this->customFieldDefinitions() as $definition) {
            $row[$definition->key] = $record[$definition->key] ?? null;
        }

        return $row;
    }

    /**
     * Write this entity_type's active custom-field values onto the row the
     * import just created/adopted (spec 0021 auto-persistence), taking ONLY
     * the keys actually present on the external record — a key the external
     * system never sent is left untouched, never nulled out. No-op when
     * nothing is present (including "no active definitions", the common
     * case), so every existing source stays behaviourally unchanged until an
     * admin defines a custom field for its entity_type.
     *
     * @param  array<string, mixed>  $record
     */
    protected function persistCustomFields(Model $model, array $record): void
    {
        $values = [];

        foreach ($this->customFieldDefinitions() as $definition) {
            if (array_key_exists($definition->key, $record)) {
                $values[$definition->key] = $record[$definition->key];
            }
        }

        if ($values === []) {
            return;
        }

        app(CustomFieldWriter::class)->write($model, $this->key(), $values);
    }

    /**
     * Active custom-field definitions for this source's entity_type.
     * Memoized per request by the provider itself, so calling this from
     * columns()/mapRow()/persistCustomFields() costs at most one query per
     * entity_type per request.
     *
     * @return Collection<int, CustomFieldDefinition>
     */
    private function customFieldDefinitions(): Collection
    {
        return app(CustomFieldProvider::class)->definitionsFor($this->key());
    }
}
