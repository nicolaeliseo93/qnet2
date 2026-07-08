<?php

declare(strict_types=1);

namespace App\CustomFields;

/**
 * Request-scoped holder for the incoming `custom_fields` payload (spec 0021
 * — INNESTO WRITE): populated by App\Http\Middleware\CaptureCustomFields and
 * drained by App\Models\Concerns\HasCustomFields' `saving`/`saved` observers
 * via CustomFieldValidator/CustomFieldWriter. Bound scoped/singleton per
 * request (see AppServiceProvider) so every consumer within the same request
 * shares the same instance.
 *
 * Single-primary-entity-per-request assumption: a single HTTP request can
 * `save()` MULTIPLE Eloquent models (e.g. a Company plus its owned Address),
 * but the `custom_fields` payload targets exactly ONE of them — the
 * request's primary resource. `pull()` is DESTRUCTIVE (it clears the bag
 * after returning its contents) precisely so the FIRST custom-fieldable
 * model saved during the request consumes the payload exactly once; any
 * further model saved later in the same request then sees an empty bag and
 * no-ops, instead of re-applying the same values to an unrelated entity.
 * `values()`/`has()` are non-destructive PEEKS (used by the validator, which
 * runs in `saving` — before the owning model has actually persisted),
 * reserving the one destructive `pull()` for the writer's `saved` observer.
 */
class CustomFieldRequestBag
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    private bool $consumed = false;

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        $this->values = $values;
        $this->consumed = false;
    }

    /**
     * Non-destructive read of the pending values, or an empty array once the
     * bag has been pulled.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->consumed ? [] : $this->values;
    }

    /**
     * Whether there is a non-empty, not-yet-consumed payload pending.
     */
    public function has(): bool
    {
        return ! $this->consumed && $this->values !== [];
    }

    /**
     * Destructive read: returns the current values and marks the bag
     * consumed, so a second custom-fieldable model saved later in the same
     * request cannot re-consume the same payload (see class docblock).
     *
     * @return array<string, mixed>
     */
    public function pull(): array
    {
        $values = $this->values();
        $this->consumed = true;
        $this->values = [];

        return $values;
    }
}
