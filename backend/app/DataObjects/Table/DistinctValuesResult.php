<?php

namespace App\DataObjects\Table;

/**
 * Result of an Excel-like distinct-values query for a single column: the
 * capped list of values plus whether more exist beyond the cap.
 *
 * Declared DTO (mirrors RowsResult) so the Service/Controller contract is
 * explicit and type-safe — see standards/architecture.md → Data Transfer
 * Objects.
 */
final readonly class DistinctValuesResult
{
    /**
     * @param  array<int, string>  $values
     */
    public function __construct(
        public array $values,
        public bool $hasMore,
    ) {}
}
