<?php

namespace App\Imports;

use App\Models\User;

/**
 * Runs ONE definition's validateRow() plus the generic natural-key dedup
 * (existing DB rows AND intra-file duplicates) over a stream of CSV rows.
 *
 * Shared by ValidateImportJob (dry-run) and ProcessImportJob (commit) so the
 * two phases can never drift: a row valid in phase 1 is valid in phase 2 —
 * module authors write the field rules ONCE (validateRow()/dedupKey()/
 * existsInDatabase()) and get identical dedup semantics for free.
 *
 * In-file dedup state ($seenKeys) lives on the INSTANCE, not the definition:
 * a fresh ImportRowProcessor is constructed per job run (see
 * ValidateImportJob/ProcessImportJob), so one run's seen keys never leak into
 * another's.
 */
class ImportRowProcessor
{
    /** @var array<string, true> dedup keys already seen in THIS run */
    private array $seenKeys = [];

    public function __construct(
        private readonly ImportDefinition $definition,
        private readonly User $actor,
    ) {}

    /**
     * @param  array<string, string>  $row  column id => raw CSV value
     */
    public function process(int $rowNumber, array $row): RowOutcome
    {
        $errors = $this->definition->validateRow($row, new ImportRowContext($rowNumber, $this->actor));

        if ($errors === []) {
            $errors = $this->dedupErrors($row);
        }

        return new RowOutcome($rowNumber, $row, $errors);
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function dedupErrors(array $row): array
    {
        $key = $this->definition->dedupKey($row);

        if ($key === null) {
            return [];
        }

        if (isset($this->seenKeys[$key])) {
            return ['Duplicate row within the file.'];
        }

        if ($this->definition->existsInDatabase($key)) {
            return ['A record with this value already exists.'];
        }

        $this->seenKeys[$key] = true;

        return [];
    }
}
