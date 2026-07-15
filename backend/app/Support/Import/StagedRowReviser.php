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
 * spec 0033 AC-017; extended by delta D-2026-07-15-placeholder-review-fields)
 * by re-running it through the SAME StagedRowBuilder pipeline (recognizers ->
 * placeholder -> validateRow -> resolveDuplicate) StageImportJob used to
 * stage it originally — never a parallel/duplicated validation path.
 *
 * Unlike the original B5 implementation, this does NOT rebuild raw file
 * values by inverting column_mapping: the edited `values` are field-id-keyed
 * (the review grid edits the FINAL persisted fields, e.g. `first_name`/
 * `last_name`, not the raw `full_name` column they were split from — a
 * recognizer-derived field has no raw column of its own to reconstruct). It
 * merges the edited values directly onto the row's existing `mapped_values`/
 * `extra_values` and replays StagedRowBuilder::resolve() from there, so
 * recognizers skip fields the edit (or the original staging) already
 * populated and the placeholder only re-applies to a field still blank.
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

        [$mappedValues, $extraValues] = $this->mergeEditedValues($row, $columnMapping, $editedValues);

        $builder = new StagedRowBuilder($definition, $actor, $columnMapping, $dedupMode);
        $outcome = $builder->resolve($row->row_number, $mappedValues, $extraValues);

        $row->update([
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
     * Overlays `editedValues` onto the row's current mapped/extra values: a
     * key naming a file column mapped to `__extra__` on this run goes to
     * extraValues (keyed by that same original column name, mirroring
     * StagedRowBuilder::applyMapping()); every other key is a field id and
     * goes to mappedValues.
     *
     * @param  array<string, string>  $columnMapping
     * @param  array<string, string>  $editedValues
     * @return array{0: array<string, mixed>, 1: array<string, string>|null}
     */
    private function mergeEditedValues(ImportRunRow $row, array $columnMapping, array $editedValues): array
    {
        $mappedValues = $row->mapped_values ?? [];
        $extraValues = $row->extra_values ?? [];

        $extraColumnKeys = array_keys(array_filter(
            $columnMapping,
            static fn (string $target): bool => $target === StagedRowBuilder::EXTRA_TARGET,
        ));

        foreach ($editedValues as $key => $value) {
            if (in_array($key, $extraColumnKeys, true)) {
                $extraValues[$key] = $value;

                continue;
            }

            $mappedValues[$key] = $value;
        }

        return [$mappedValues, $extraValues === [] ? null : $extraValues];
    }
}
