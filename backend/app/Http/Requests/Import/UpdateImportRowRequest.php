<?php

namespace App\Http\Requests\Import;

use App\Imports\ImportDefinition;
use App\Imports\ImportRegistry;
use App\Imports\Staging\StagedRowBuilder;
use App\Models\ImportRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates PATCH /api/imports/{domain}/{importRun}/rows/{row} (spec 0033,
 * AC-017): the inline edit of ONE staged row's values, keyed by an allow-list
 * built from the bound run — the definition's `fields()` ids PLUS the file
 * column keys currently mapped to `__extra__` on THIS run's `column_mapping`
 * (mirroring how ImportRunRowResource/StagedRowBuilder key extra values by
 * their original column name, never a synthetic "extra field id"). Any other
 * key is rejected — the same allow-list discipline as ConfigureImportRequest.
 *
 * The {domain}/{importRun} route segments resolve BEFORE any rule below runs
 * (unknown domain -> 404 via bootstrap/app.php; unknown/unbound importRun ->
 * 404 via route model binding), mirroring ConfigureImportRequest.
 * Authorization, run ownership and the `reviewing` status guard are NOT
 * handled here — they stay in the controller/Service.
 */
class UpdateImportRowRequest extends FormRequest
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
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $values = $this->input('values');

            if (! is_array($values)) {
                return;
            }

            $allowedKeys = $this->allowedValueKeys();

            foreach (array_keys($values) as $key) {
                if (! in_array($key, $allowedKeys, true)) {
                    $validator->errors()->add(
                        "values.{$key}",
                        "The field [{$key}] is not mapped nor an extra column for this import.",
                    );
                }
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function allowedValueKeys(): array
    {
        $fieldIds = array_map(
            static fn (array $field): string => $field['id'],
            $this->definition()->fields(),
        );

        $importRun = $this->route('importRun');
        $mapping = $importRun instanceof ImportRun ? ($importRun->column_mapping ?? []) : [];

        $extraKeys = array_keys(array_filter(
            $mapping,
            static fn (string $target): bool => $target === StagedRowBuilder::EXTRA_TARGET,
        ));

        return [...$fieldIds, ...$extraKeys];
    }

    private function definition(): ImportDefinition
    {
        if ($this->resolvedDefinition === null) {
            $this->resolvedDefinition = app(ImportRegistry::class)->resolve((string) $this->route('domain'));
        }

        return $this->resolvedDefinition;
    }
}
