<?php

namespace App\Imports;

/**
 * The result of running ImportRowProcessor::process() on one CSV row: its
 * 1-based row number, the raw (header-mapped) values, and the motivated
 * errors that reject it (empty = valid). Shared shape consumed by both
 * ValidateImportJob (preview + report) and ProcessImportJob (creation +
 * report), so the two phases can never drift on what "valid" means.
 */
final readonly class RowOutcome
{
    /**
     * @param  array<string, string>  $values
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public int $rowNumber,
        public array $values,
        public array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
