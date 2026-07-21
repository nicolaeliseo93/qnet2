<?php

namespace App\Http\Requests\Import;

use App\Models\ImportRun;
use App\Models\ImportRunRow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates PATCH /api/imports/{domain}/{importRun}/rows/assign (spec 0045
 * bulk increment, extended to a COMBINED operator+site assignment): bulk-
 * assign an operator and/or an operational site to a batch of staged rows —
 * distinct from the single-row PATCH .../rows/{row}. AG Grid
 * `getServerSideSelectionState()` semantics: `row_ids` are the rows to
 * TARGET when `select_all` is false (required, non-empty), the rows to
 * EXCLUDE when `select_all` is true (optional, empty = every row in the
 * run). Bulk-only ASSIGNS — `operator_id`/`operational_site_id` are never
 * nullable; clearing a single row's override stays PATCH .../rows/{row}. At
 * least ONE of the two must be present.
 *
 * `row_ids` existence is checked SCOPED to the bound {importRun} (a plain
 * `exists:import_run_rows,id` would let an id from ANOTHER run through —
 * an IDOR/cross-run leak), mirroring UpdateImportRowRequest's own allow-list
 * discipline. Ownership/authorization/the run's `reviewing` status guard are
 * NOT handled here — they stay in the controller, same convention as every
 * other Import* request.
 */
class BulkAssignRequest extends FormRequest
{
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
            'operator_id' => ['sometimes', 'integer', 'exists:users,id'],
            'operational_site_id' => ['sometimes', 'integer', 'exists:operational_sites,id'],
            'select_all' => ['nullable', 'boolean'],
            'row_ids' => ['array'],
            'row_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateAtLeastOneAssignment($validator);
            $this->validateRowIdsRequiredWhenNotSelectAll($validator);
            $this->validateRowIdsBelongToRun($validator);
        });
    }

    private function validateAtLeastOneAssignment(Validator $validator): void
    {
        if ($this->has('operator_id') || $this->has('operational_site_id')) {
            return;
        }

        $validator->errors()->add('operator_id', 'At least one of operator_id or operational_site_id is required.');
    }

    private function validateRowIdsRequiredWhenNotSelectAll(Validator $validator): void
    {
        if ($this->selectAll() || $this->rowIds() !== []) {
            return;
        }

        $validator->errors()->add('row_ids', 'row_ids is required when select_all is false.');
    }

    /**
     * Anti-IDOR: every submitted id must belong to THIS run — a plain
     * `exists:import_run_rows,id` rule would accept an id from another
     * user's/domain's run.
     */
    private function validateRowIdsBelongToRun(Validator $validator): void
    {
        $rowIds = $this->rowIds();

        if ($rowIds === []) {
            return;
        }

        $importRun = $this->route('importRun');

        if (! $importRun instanceof ImportRun) {
            return;
        }

        $matchedCount = ImportRunRow::query()
            ->where('import_run_id', $importRun->id)
            ->whereIn('id', $rowIds)
            ->count();

        if ($matchedCount !== count(array_unique($rowIds))) {
            $validator->errors()->add('row_ids', 'One or more row_ids do not belong to this import run.');
        }
    }

    public function selectAll(): bool
    {
        return $this->boolean('select_all', false);
    }

    /**
     * @return array<int, int>
     */
    public function rowIds(): array
    {
        $rowIds = $this->input('row_ids', []);

        return is_array($rowIds) ? array_map('intval', $rowIds) : [];
    }

    public function operatorId(): ?int
    {
        return $this->has('operator_id') ? (int) $this->input('operator_id') : null;
    }

    public function operationalSiteId(): ?int
    {
        return $this->has('operational_site_id') ? (int) $this->input('operational_site_id') : null;
    }
}
