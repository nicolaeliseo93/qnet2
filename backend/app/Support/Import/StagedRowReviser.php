<?php

namespace App\Support\Import;

use App\Enums\ImportDedupMode;
use App\Imports\ImportDefinition;
use App\Imports\Staging\StagedRowBuilder;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;

/**
 * Re-validates ONE staged row after an inline edit (PATCH .../rows/{row},
 * spec 0033, AC-017) by re-running it through the SAME StagedRowBuilder
 * pipeline (mapping/recognizers/validateRow/resolveDuplicate) StageImportJob
 * used to stage it originally — never a parallel/duplicated validation path.
 *
 * StagedRowBuilder::build() takes RAW file-column-keyed values, not the
 * field-id-keyed `values` the wizard's edit form sends. mergeEditedIntoRaw()
 * bridges the two: it overlays each edited value onto the SAME raw column
 * key the run's `column_mapping` already resolves it from (inverting the
 * mapping), so build() replays identically to staging, just with the user's
 * correction substituted in.
 */
final class StagedRowReviser
{
    /**
     * @param  array<string, string>  $editedValues  field id (or extra column key) => new value
     */
    public function revise(ImportDefinition $definition, User $actor, ImportRun $run, ImportRunRow $row, array $editedValues): ImportRunRow
    {
        $columnMapping = $run->column_mapping ?? [];
        $dedupMode = ImportDedupMode::from($run->dedup_strategy ?? ImportDedupMode::CreateOnly->value);

        $rawValues = $this->mergeEditedIntoRaw($row->raw_values ?? [], $columnMapping, $editedValues);

        $builder = new StagedRowBuilder($definition, $actor, $columnMapping, $dedupMode);
        $outcome = $builder->build($row->row_number, $rawValues);

        $row->update([
            'raw_values' => $rawValues,
            'mapped_values' => $outcome->mappedValues,
            'extra_values' => $outcome->extraValues,
            'resolved' => $outcome->resolved,
            'status' => $outcome->status,
            'messages' => $outcome->messages,
            'duplicate_of_id' => $outcome->duplicateOfId,
            'is_edited' => true,
        ]);

        return $row->fresh();
    }

    /**
     * @param  array<string, string>  $rawValues
     * @param  array<string, string>  $columnMapping
     * @param  array<string, string>  $editedValues
     * @return array<string, string>
     */
    private function mergeEditedIntoRaw(array $rawValues, array $columnMapping, array $editedValues): array
    {
        foreach ($columnMapping as $columnKey => $target) {
            if ($target === StagedRowBuilder::EXTRA_TARGET) {
                if (array_key_exists($columnKey, $editedValues)) {
                    $rawValues[$columnKey] = $editedValues[$columnKey];
                }

                continue;
            }

            if ($target === StagedRowBuilder::IGNORE_TARGET) {
                continue;
            }

            if (array_key_exists($target, $editedValues)) {
                $rawValues[$columnKey] = $editedValues[$target];
            }
        }

        return $rawValues;
    }
}
