<?php

namespace App\Http\Resources\Abstracts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base resource owning the for-select item contract (ADR 0011):
 *
 *   { id, label, subtitle?, avatar?, meta? }
 *
 * `id` + `label` are mandatory; `subtitle`, `avatar`, `meta` are optional and a
 * concrete resource only emits the ones it has (null optionals are omitted, not
 * serialized as null). Keys are snake_case, consistent with every client-facing
 * key on this backend.
 *
 * Concrete resources implement {@see forSelectItem()} mapping their entity to the
 * shape; the envelope/omission rules live here so every select is identical.
 *
 * `meta` is a small, flat, non-sensitive presentation bag — NOT an escape hatch
 * for full entity data.
 */
abstract class ForSelectResource extends JsonResource
{
    /**
     * Map the underlying entity to the for-select item shape.
     *
     * Required keys: `id`, `label`. Optional keys: `subtitle`, `avatar`, `meta`.
     * Optionals returned as null are stripped from the final payload.
     *
     * @return array<string, mixed>
     */
    abstract protected function forSelectItem(Request $request): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->forSelectItem($request);

        // Omit optional keys that are null so the payload stays minimal and the
        // contract (id + label always present, optionals present-or-absent) holds.
        return array_filter(
            $item,
            static fn (mixed $value, string $key): bool => in_array($key, ['id', 'label'], true) || $value !== null,
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
