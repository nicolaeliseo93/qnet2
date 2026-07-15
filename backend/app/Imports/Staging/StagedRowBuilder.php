<?php

namespace App\Imports\Staging;

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Imports\ImportDefinition;
use App\Imports\ImportRowContext;
use App\Imports\Recognition\RowRecognizer;
use App\Models\User;

/**
 * Turns ONE raw file row into a StageOutcome (spec 0033): applies the run's
 * `column_mapping` (column key -> field id | __ignore__ | __extra__), runs
 * the definition's recognizers(), validates the mapped values, then resolves
 * the row's status from resolveDuplicate() + the run's dedup strategy. A
 * fresh instance is constructed per job run (mirrors ImportRowProcessor), so
 * it carries no cross-row state.
 */
final class StagedRowBuilder
{
    /** Column mapping target meaning "do not import this file column". */
    public const string IGNORE_TARGET = '__ignore__';

    /** Column mapping target meaning "store this file column verbatim as an extra field". */
    public const string EXTRA_TARGET = '__extra__';

    /**
     * @param  array<string, string>  $columnMapping  column key => field id | IGNORE_TARGET | EXTRA_TARGET
     */
    public function __construct(
        private readonly ImportDefinition $definition,
        private readonly User $actor,
        private readonly array $columnMapping,
        private readonly ImportDedupMode $dedupMode,
    ) {}

    /**
     * @param  array<string, string>  $rawValues  column key => raw file value
     */
    public function build(int $rowNumber, array $rawValues): StageOutcome
    {
        // Step 1: split the raw row into mapped field values and extra values.
        [$mappedValues, $extraValues] = $this->applyMapping($rawValues);

        // Step 2: run the definition's recognizers, merging resolved values
        // into BOTH the mapped values (so validateRow/persistRow see them
        // directly) and the row's own `resolved` record (for the review UI).
        $context = new ImportRowContext($rowNumber, $this->actor);
        [$resolved, $messages, $needsReview] = $this->runRecognizers($context, $mappedValues);
        $mappedValues = [...$mappedValues, ...$resolved];

        // Step 3: field-level validation rejects the row regardless of dedup.
        $errors = $this->definition->validateRow($mappedValues, $context);

        if ($errors !== []) {
            return new StageOutcome(
                mappedValues: $mappedValues,
                extraValues: $extraValues === [] ? null : $extraValues,
                resolved: $resolved === [] ? null : $resolved,
                status: ImportRowStatus::Error,
                messages: [...$messages, ...$errors],
                duplicateOfId: null,
            );
        }

        // Step 4: resolve the row's status from its duplicate outcome.
        $duplicateOfId = $this->definition->resolveDuplicate($mappedValues);

        return new StageOutcome(
            mappedValues: $mappedValues,
            extraValues: $extraValues === [] ? null : $extraValues,
            resolved: $resolved === [] ? null : $resolved,
            status: $this->resolveStatus($duplicateOfId, $needsReview),
            messages: $messages === [] ? null : $messages,
            duplicateOfId: $duplicateOfId,
        );
    }

    /**
     * @param  array<string, string>  $rawValues
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function applyMapping(array $rawValues): array
    {
        $mapped = [];
        $extra = [];

        foreach ($this->columnMapping as $columnKey => $target) {
            if (! array_key_exists($columnKey, $rawValues)) {
                continue;
            }

            $value = $rawValues[$columnKey];

            match ($target) {
                self::IGNORE_TARGET => null,
                self::EXTRA_TARGET => $extra[$columnKey] = $value,
                default => $mapped[$target] = $value,
            };
        }

        return [$mapped, $extra];
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array{0: array<string, mixed>, 1: array<int, string>, 2: bool}
     */
    private function runRecognizers(ImportRowContext $context, array $mapped): array
    {
        $resolved = [];
        $messages = [];
        $needsReview = false;

        foreach ($this->definition->recognizers() as $recognizerClass) {
            /** @var RowRecognizer $recognizer */
            $recognizer = app($recognizerClass);
            $result = $recognizer->recognize($context, [...$mapped, ...$resolved]);

            $resolved = [...$resolved, ...$result->resolved];
            $messages = [...$messages, ...$result->messages];
            $needsReview = $needsReview || $result->needsReview;
        }

        return [$resolved, $messages, $needsReview];
    }

    private function resolveStatus(?int $duplicateOfId, bool $needsReview): ImportRowStatus
    {
        if ($duplicateOfId !== null) {
            return match ($this->dedupMode) {
                ImportDedupMode::Ignore => ImportRowStatus::Skipped,
                ImportDedupMode::Manual => ImportRowStatus::Duplicate,
                ImportDedupMode::CreateNew, ImportDedupMode::UpdateExisting, ImportDedupMode::CreateOnly => ImportRowStatus::Valid,
            };
        }

        return $needsReview ? ImportRowStatus::Warning : ImportRowStatus::Valid;
    }
}
