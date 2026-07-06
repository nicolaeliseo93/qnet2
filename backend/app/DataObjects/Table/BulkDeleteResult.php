<?php

namespace App\DataObjects\Table;

/**
 * Result of a generic bulk-delete run (POST /api/tables/{domain}/bulk-delete):
 * how many rows were actually deleted plus the per-id failure detail.
 *
 * Declared DTO (no "magic flying array") so the Service -> Controller contract
 * stays explicit and type-safe, mirroring RowsResult.
 */
final readonly class BulkDeleteResult
{
    /**
     * @param  array<int, array{id: int, reason: string}>  $failed
     */
    public function __construct(
        public int $deleted,
        public array $failed,
    ) {}
}
