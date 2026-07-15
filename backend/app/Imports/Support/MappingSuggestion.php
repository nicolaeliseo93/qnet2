<?php

namespace App\Imports\Support;

/**
 * ColumnMapper::suggest() outcome (spec 0033, AC-003): a pure proposal the
 * wizard's mapping step starts from — the actor can still edit it before
 * PUT .../configure persists the final `column_mapping`.
 */
final readonly class MappingSuggestion
{
    /**
     * @param  array<string, string>  $mapping  column key => field id (unambiguous matches only)
     * @param  array<int, string>  $missingRequired  required field ids with no matched column
     * @param  array<int, string>  $duplicateColumns  column keys sharing their file header name
     * @param  array<int, string>  $unusedColumns  column keys matching no field
     * @param  array<string, array<int, string>>  $conflicts  field id => column keys all matching it
     */
    public function __construct(
        public array $mapping,
        public array $missingRequired,
        public array $duplicateColumns,
        public array $unusedColumns,
        public array $conflicts,
    ) {}
}
