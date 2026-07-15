<?php

namespace App\Imports\Staging;

use App\Enums\ImportRowStatus;

/**
 * The result of running StagedRowBuilder::build() on one file row (spec 0033):
 * the shape StageImportJob persists verbatim onto one `import_run_rows` row.
 */
final readonly class StageOutcome
{
    /**
     * @param  array<string, mixed>  $mappedValues
     * @param  array<string, string>|null  $extraValues
     * @param  array<string, mixed>|null  $resolved
     * @param  array<int, string>|null  $messages
     */
    public function __construct(
        public array $mappedValues,
        public ?array $extraValues,
        public ?array $resolved,
        public ImportRowStatus $status,
        public ?array $messages,
        public ?int $duplicateOfId,
    ) {}
}
