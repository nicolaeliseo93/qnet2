<?php

namespace App\Support\Import;

use App\Enums\ImportRowResolution;
use App\Enums\ImportRowStatus;
use App\Imports\Staging\StagedRowBuilder;
use App\Models\ImportRun;
use App\Models\ImportRunRow;

/**
 * Builds the GET .../summary (spec 0033) and the PATCH .../rows/{row}
 * `counts` payloads — both read-only projections of an ImportRun's current
 * counters/column_mapping, kept in one place so the two endpoints never
 * drift on field names.
 */
final class ImportRunSummaryBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function summary(ImportRun $importRun): array
    {
        [$mappedFields, $extraFields] = $this->splitMapping($importRun->column_mapping ?? []);

        return [
            'total_rows' => $importRun->total_rows,
            'valid_rows' => $importRun->valid_rows,
            'warning_rows' => $importRun->warning_rows,
            'error_rows' => $importRun->invalid_rows,
            'duplicate_rows' => $importRun->duplicate_rows,
            'modified_rows' => $importRun->modified_rows,
            'mapped_fields' => $mappedFields,
            'extra_fields' => $extraFields,
            'global_config' => $importRun->global_config ?? [],
            'dedup_strategy' => $importRun->dedup_strategy,
            'warnings' => $this->collectWarningMessages($importRun),
            'duplicate_resolutions' => $this->duplicateResolutions($importRun),
        ];
    }

    /**
     * The PATCH .../rows/{row} `counts` shape (spec 0033 data_contract) —
     * `total`, NOT `total_rows` (summary()'s key), by contract.
     *
     * @return array<string, int|null>
     */
    public function counts(ImportRun $importRun): array
    {
        return [
            'total' => $importRun->total_rows,
            'valid_rows' => $importRun->valid_rows,
            'warning_rows' => $importRun->warning_rows,
            'error_rows' => $importRun->invalid_rows,
            'duplicate_rows' => $importRun->duplicate_rows,
            'modified_rows' => $importRun->modified_rows,
        ];
    }

    /**
     * @param  array<string, string>  $mapping
     * @return array{0: array<int, array{column: string, field: string}>, 1: array<int, string>}
     */
    private function splitMapping(array $mapping): array
    {
        $mappedFields = [];
        $extraFields = [];

        foreach ($mapping as $column => $target) {
            match (true) {
                $target === StagedRowBuilder::IGNORE_TARGET => null,
                $target === StagedRowBuilder::EXTRA_TARGET => $extraFields[] = $column,
                default => $mappedFields[] = ['column' => $column, 'field' => $target],
            };
        }

        return [$mappedFields, $extraFields];
    }

    /**
     * The confirm-step recap of the run's CURRENT `duplicate` rows, grouped
     * by the operator's per-row resolution (spec 0036) — `unresolved` counts
     * a still-null `resolution`, never blocking the confirm itself.
     *
     * @return array{skip: int, create: int, update: int, unresolved: int}
     */
    private function duplicateResolutions(ImportRun $importRun): array
    {
        $counts = ImportRunRow::query()
            ->where('import_run_id', $importRun->id)
            ->where('status', ImportRowStatus::Duplicate)
            ->selectRaw('resolution, COUNT(*) as aggregate')
            ->groupBy('resolution')
            ->pluck('aggregate', 'resolution');

        return [
            'skip' => (int) ($counts[ImportRowResolution::Skip->value] ?? 0),
            'create' => (int) ($counts[ImportRowResolution::Create->value] ?? 0),
            'update' => (int) ($counts[ImportRowResolution::Update->value] ?? 0),
            'unresolved' => (int) ($counts[''] ?? 0),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function collectWarningMessages(ImportRun $importRun): array
    {
        return ImportRunRow::query()
            ->where('import_run_id', $importRun->id)
            ->where('status', ImportRowStatus::Warning)
            ->get()
            ->flatMap(static fn (ImportRunRow $row): array => $row->messages ?? [])
            ->unique()
            ->values()
            ->all();
    }
}
