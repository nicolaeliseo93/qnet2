<?php

namespace App\Imports\Staging;

use App\Enums\ImportRowStatus;
use App\Imports\RowOutcome;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Services\ImportService;
use Illuminate\Support\Collection;

/**
 * Writes the downloadable errors CSV report for a wizard import run (spec
 * 0033) by delegating to the legacy `ImportService::writeErrorReport()`
 * (its signature is untouched — see the class docblock there): every
 * `import_run_rows` row rejected at staging (error/skipped) is reshaped into
 * a RowOutcome, plus any commit-time failure StageImportJob/
 * ProcessStagedImportJob collected. Called by BOTH jobs, so a commit-time
 * failure never overwrites the staging-time report without it — the report
 * is always rebuilt from the run's full current row set.
 */
final class StagingErrorReporter
{
    public function __construct(private readonly ImportService $importService) {}

    /**
     * @param  array<int, array{row: ImportRunRow, message: string}>  $commitFailures
     */
    public function write(ImportRun $run, array $commitFailures = []): void
    {
        $stagedRejected = $run->rows()
            ->whereIn('status', [ImportRowStatus::Error, ImportRowStatus::Skipped])
            ->orderBy('row_number')
            ->get();

        $outcomes = [
            ...$this->toOutcomes($stagedRejected),
            ...$this->commitFailuresToOutcomes($commitFailures),
        ];

        $this->importService->writeErrorReport($run, $this->reportColumns($run), $outcomes);
    }

    /**
     * @param  Collection<int, ImportRunRow>  $rows
     * @return array<int, RowOutcome>
     */
    private function toOutcomes(Collection $rows): array
    {
        return $rows
            ->map(static fn (ImportRunRow $row): RowOutcome => new RowOutcome($row->row_number, $row->raw_values, $row->messages ?? []))
            ->all();
    }

    /**
     * @param  array<int, array{row: ImportRunRow, message: string}>  $commitFailures
     * @return array<int, RowOutcome>
     */
    private function commitFailuresToOutcomes(array $commitFailures): array
    {
        return array_map(
            static fn (array $failure): RowOutcome => new RowOutcome($failure['row']->row_number, $failure['row']->raw_values, [$failure['message']]),
            $commitFailures,
        );
    }

    /**
     * @return array<int, string>
     */
    private function reportColumns(ImportRun $run): array
    {
        $firstRow = $run->rows()->orderBy('row_number')->first();

        return $firstRow !== null ? array_keys($firstRow->raw_values) : [];
    }
}
