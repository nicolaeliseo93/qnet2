<?php

namespace App\Http\Requests\Import;

use App\Enums\ImportDedupMode;
use App\Imports\ImportDefinition;
use App\Imports\ImportRegistry;
use App\Imports\Staging\StagedRowBuilder;
use App\Imports\Support\ColumnAnalysis;
use App\Models\ImportRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates PUT /api/imports/{domain}/{importRun}/configure (spec 0033): the
 * wizard's configuration step (column mapping + global config + dedup
 * strategy), checked against the resolved ImportDefinition's OWN catalogue —
 * never a hardcoded list. `column_mapping` KEYS are an allow-list of the
 * bound run's OWN detected column keys (ColumnAnalysis::columnKeys() over
 * `detected_columns` — the deterministic "bare name, or name#index on a
 * later duplicate" key, never the raw column name: two identically-named
 * file columns must never collapse onto the same mapping entry); VALUES are
 * an allow-list of `fields()` ids plus `__ignore__` (always) and `__extra__`
 * (only when supportsExtraFields()). Required fields() and required
 * globalConfig() entries must be covered; `dedup_strategy` must be one of
 * dedupModes().
 *
 * The {domain} route segment resolves an UNKNOWN domain to a
 * ModelNotFoundException (-> 404, see bootstrap/app.php) BEFORE any rule
 * below runs, mirroring TableRowsRequest. Authorization and the run's
 * current-status guard (`configuring`) are NOT handled here — they stay in
 * the controller/Service, same convention as every other Import* request.
 */
class ConfigureImportRequest extends FormRequest
{
    private ?ImportDefinition $resolvedDefinition = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'column_mapping' => ['required', 'array'],
            'column_mapping.*' => ['required', 'string'],
            'global_config' => ['sometimes', 'array'],
            'dedup_strategy' => ['required', 'string', Rule::in($this->allowedDedupValues())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $definition = $this->definition();

            $this->assertMappingKeysAndTargetsAllowed($validator, $definition);
            $this->assertRequiredFieldsMapped($validator, $definition);
            $this->assertRequiredGlobalConfigPresent($validator, $definition);
        });
    }

    private function assertMappingKeysAndTargetsAllowed(Validator $validator, ImportDefinition $definition): void
    {
        $mapping = $this->input('column_mapping');

        if (! is_array($mapping)) {
            return;
        }

        $allowedKeys = $this->allowedColumnKeys();
        $allowedTargets = $this->allowedMappingTargets($definition);

        foreach ($mapping as $columnKey => $target) {
            if (! in_array((string) $columnKey, $allowedKeys, true)) {
                $validator->errors()->add(
                    "column_mapping.{$columnKey}",
                    "The column [{$columnKey}] is not part of this run's detected columns.",
                );

                continue;
            }

            if (! is_string($target) || ! in_array($target, $allowedTargets, true)) {
                $validator->errors()->add(
                    "column_mapping.{$columnKey}",
                    "The mapping target for column [{$columnKey}] is not allowed.",
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedColumnKeys(): array
    {
        $importRun = $this->route('importRun');
        $columns = $importRun instanceof ImportRun ? ($importRun->detected_columns ?? []) : [];

        return ColumnAnalysis::columnKeys($columns);
    }

    private function assertRequiredFieldsMapped(Validator $validator, ImportDefinition $definition): void
    {
        $mapping = $this->input('column_mapping');
        $mappedTargets = is_array($mapping) ? array_values($mapping) : [];

        foreach ($definition->fields() as $field) {
            if (($field['required'] ?? false) && ! in_array($field['id'], $mappedTargets, true)) {
                $validator->errors()->add('column_mapping', "Required field [{$field['id']}] is not mapped.");
            }
        }
    }

    private function assertRequiredGlobalConfigPresent(Validator $validator, ImportDefinition $definition): void
    {
        $globalConfig = $this->input('global_config', []);

        foreach ($definition->globalConfig() as $field) {
            if (! ($field['required'] ?? false)) {
                continue;
            }

            $value = is_array($globalConfig) ? ($globalConfig[$field['id']] ?? null) : null;

            if ($value === null || $value === '') {
                $validator->errors()->add("global_config.{$field['id']}", "The [{$field['id']}] global field is required.");
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedMappingTargets(ImportDefinition $definition): array
    {
        $targets = [
            StagedRowBuilder::IGNORE_TARGET,
            ...array_map(static fn (array $field): string => $field['id'], $definition->fields()),
        ];

        if ($definition->supportsExtraFields()) {
            $targets[] = StagedRowBuilder::EXTRA_TARGET;
        }

        return $targets;
    }

    /**
     * @return array<int, string>
     */
    private function allowedDedupValues(): array
    {
        return array_map(
            static fn (ImportDedupMode $mode): string => $mode->value,
            $this->definition()->dedupModes(),
        );
    }

    private function definition(): ImportDefinition
    {
        if ($this->resolvedDefinition === null) {
            $this->resolvedDefinition = app(ImportRegistry::class)->resolve((string) $this->route('domain'));
        }

        return $this->resolvedDefinition;
    }
}
