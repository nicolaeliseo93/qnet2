<?php

namespace App\Imports;

/**
 * Builds the bounded preview block persisted on ImportRun::preview (spec
 * 0012 data_contract — GET /api/imports/{domain}/{importRun}): a capped
 * sample of valid rows and a capped sample of rejected rows with their
 * motivated errors, so the endpoint response never grows with the file size.
 * The full rejected set (not just the sample) goes to the errors CSV report
 * instead — see ImportService::writeErrorReport().
 */
final class ImportPreview
{
    /**
     * @param  array<int, string>  $columns
     * @param  array<int, RowOutcome>  $valid
     * @param  array<int, RowOutcome>  $invalid
     * @return array{columns: array<int, string>, valid_sample: array<int, array<string, string>>, invalid_sample: array<int, array{row_number: int, values: array<string, string>, errors: array<int, string>}>}
     */
    public static function build(array $columns, array $valid, array $invalid): array
    {
        return [
            'columns' => $columns,
            'valid_sample' => array_map(
                static fn (RowOutcome $outcome): array => $outcome->values,
                array_slice($valid, 0, (int) config('imports.preview_valid')),
            ),
            'invalid_sample' => array_map(
                static fn (RowOutcome $outcome): array => [
                    'row_number' => $outcome->rowNumber,
                    'values' => $outcome->values,
                    'errors' => $outcome->errors,
                ],
                array_slice($invalid, 0, (int) config('imports.preview_invalid')),
            ),
        ];
    }
}
