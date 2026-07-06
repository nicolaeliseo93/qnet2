<?php

namespace App\Migrations;

/**
 * The outcome of importing ONE external record (spec 0013): either it was
 * SKIPPED (its `old_id` already exists — idempotent re-import) or CREATED.
 * Both may carry non-fatal warnings (e.g. an unresolved relational reference,
 * or a note about relations back-filled on a self-healing re-import) to
 * surface in the run's report. A thrown exception from
 * MigrationSource::processRow (via AbstractMigrationSource) is a distinct,
 * FAILED outcome handled by the caller, never represented here.
 */
final readonly class MigrationRowOutcome
{
    /**
     * @param  array<int, string>  $warnings
     */
    private function __construct(
        public bool $skipped,
        public array $warnings,
    ) {}

    /**
     * @param  array<int, string>  $warnings
     */
    public static function created(array $warnings = []): self
    {
        return new self(skipped: false, warnings: $warnings);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    public static function skipped(array $warnings = []): self
    {
        return new self(skipped: true, warnings: $warnings);
    }
}
