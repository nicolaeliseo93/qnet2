<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldValidator;
use App\CustomFields\CustomFieldWriter;
use App\CustomFields\FieldTypeRegistry;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Grafts the universal custom-field write/read pipeline (spec 0021 — INNESTO
 * WRITE) onto BaseModel — added ONCE there, so every domain already
 * registered in both TableRegistry and AuthorizationRegistry becomes
 * custom-fieldable at zero per-model cost.
 *
 * Write path: `saving` validates the pending CustomFieldRequestBag payload
 * (aborting with a 422 ValidationException BEFORE anything is persisted);
 * `saved` upserts it into `custom_field_values`; `deleting` removes that row
 * (AC-013 cleanup). All three are no-ops when the model's class is not
 * registered as custom-fieldable OR the bag has nothing pending for it (see
 * CustomFieldRequestBag's single-primary-entity-per-request assumption).
 *
 * The trait NEVER reads request()/auth() directly — it only asks the bag
 * (injected via the container) whether there is pending input, keeping the
 * model itself HTTP-decoupled and unit-testable: a test can populate the bag
 * directly (`app(CustomFieldRequestBag::class)->set([...])`) and call
 * `save()`, with no HTTP request involved at all.
 */
trait HasCustomFields
{
    public static function bootHasCustomFields(): void
    {
        static::saving(static function (Model $model): void {
            app(CustomFieldValidator::class)->validateFromRequestBag($model);
        });

        static::saved(static function (Model $model): void {
            app(CustomFieldWriter::class)->writeFromRequestBag($model);
        });

        static::deleting(static function (Model $model): void {
            app(CustomFieldWriter::class)->purge($model);
        });
    }

    /**
     * This model's entity_type in the custom-fieldable registry, or null when
     * its class is not registered (TableRegistry + AuthorizationRegistry) —
     * every hook above short-circuits on null.
     */
    public function customFieldEntityType(): ?string
    {
        return app(CustomFieldEntityRegistry::class)->entityTypeForModel($this);
    }

    /**
     * The owning `custom_field_values` row — the read path consumed by
     * `getCustomFieldsAttribute()` below and, eventually, the detail
     * envelope in BaseApiController. The Table/SSRM layer joins
     * `custom_field_values` directly and does NOT use this relation.
     *
     * @return HasOne<CustomFieldValue, $this>
     */
    public function customFieldValueRow(): HasOne
    {
        return $this->hasOne(CustomFieldValue::class, 'entity_id', 'id')
            ->where('entity_type', $this->customFieldEntityType());
    }

    /**
     * The `custom_fields` accessor: un-namespaced `[key => value]` map
     * resolved from the stored JSON row through each definition's own
     * FieldTypeHandler::resolveForRead() (e.g. relation ids stay ids, enum
     * stays its raw value/array). Empty when the model is not
     * custom-fieldable or has no stored row yet.
     *
     * @return array<string, mixed>
     */
    public function getCustomFieldsAttribute(): array
    {
        $entityType = $this->customFieldEntityType();

        if ($entityType === null) {
            return [];
        }

        $row = $this->relationLoaded('customFieldValueRow')
            ? $this->getRelation('customFieldValueRow')
            : $this->customFieldValueRow()->first();

        $stored = $row?->values ?? [];
        $types = app(FieldTypeRegistry::class);

        return app(CustomFieldProvider::class)->definitionsFor($entityType)
            ->mapWithKeys(static fn (CustomFieldDefinition $definition): array => [
                $definition->key => $types->resolve($definition->type)->resolveForRead($stored[$definition->key] ?? null, $definition),
            ])
            ->all();
    }
}
