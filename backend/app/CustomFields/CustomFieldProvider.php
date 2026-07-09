<?php

declare(strict_types=1);

namespace App\CustomFields;

use App\Models\CustomFieldDefinition;
use Illuminate\Support\Collection;

/**
 * Reads the active `CustomFieldDefinition` rows for a given entity_type
 * (spec 0021), memoized per request so the decorators (T5/T6) — hit on every
 * table/meta/read request — query the tiny, indexed definitions table at most
 * once per entity_type per request.
 *
 * Only the raw, active, ordered definitions (with their options eager-loaded)
 * are returned here. Turning them into `FieldDefinition[]` (for
 * AuthorizationRegistry decoration) or column arrays (for TableRegistry
 * decoration) is the decorators' responsibility, not this provider's.
 *
 * NOTE: this used a cross-request `Cache::rememberForever` store, but a
 * serialized Eloquent Collection round-trips to `__PHP_Incomplete_Class` when
 * read back from the `database`/`file` cache stores, violating the
 * `: Collection` return type and 500-ing every custom-fieldable table/meta
 * request. The definitions table is tiny and indexed, so a request-scoped
 * in-memory memo (the provider is bound `scoped`) is both correct and cheap —
 * no serialization, no cross-request staleness.
 */
class CustomFieldProvider
{
    /**
     * Namespace prefix for every custom field key, so it can never collide
     * with a native field on the same resource (e.g. `custom.notes` vs the
     * native `notes`).
     */
    public const string KEY_PREFIX = 'custom.';

    /** @var array<string, Collection<int, CustomFieldDefinition>> */
    private array $memo = [];

    /**
     * Active definitions for the given entity_type, ordered by sort_order,
     * with options eager-loaded (enum fields need them). Memoized per request.
     *
     * @return Collection<int, CustomFieldDefinition>
     */
    public function definitionsFor(string $entityType): Collection
    {
        return $this->memo[$entityType] ??= CustomFieldDefinition::query()
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->with('options')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Drop the memoized definitions for one entity_type. Called by the admin
     * CustomFieldService whenever a definition of that entity_type is created,
     * updated or deleted (so a later read in the same request sees the change).
     */
    public function forget(string $entityType): void
    {
        unset($this->memo[$entityType]);
    }

    /**
     * Namespace a raw definition key (e.g. "notes") into its field key
     * (e.g. "custom.notes") — used wherever a custom field is exposed
     * alongside native fields (meta, columns, row payloads, permissions).
     */
    public function namespacedKey(string $key): string
    {
        return self::KEY_PREFIX.$key;
    }
}
