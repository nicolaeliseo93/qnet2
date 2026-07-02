<?php

namespace App\DataObjects\Shared;

use Illuminate\Support\Collection;

/**
 * Result of a for-select query (Service → Controller). Declared DTO so the
 * service returns a typed contract instead of a magic array — see
 * standards/architecture.md → Data Transfer Objects and ADR 0011.
 *
 * - `items`: the models for the current page PLUS the deduplicated hydrated
 *   `ids[]`, ready to be wrapped in a ForSelectResource.
 * - `total`: the searchable population size only (hydrated ids do NOT inflate it),
 *   so the frontend paginates the real result set.
 */
final readonly class ForSelectResult
{
    /**
     * @param  Collection<int, mixed>  $items
     */
    public function __construct(
        public Collection $items,
        public int $total,
        public int $offset,
        public int $limit,
    ) {}
}
