<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/imports/{domain}/{importRun}/confirm (spec 0045):
 * the optional `convert_to_opportunity` flag driving the auto-convert-to-
 * Opportunity gate (ImportService::confirmStaged() /
 * ImportOpportunityConvertibility). Ownership, authorization and the run's
 * `reviewing` status guard are NOT handled here, mirroring every other
 * Import* request.
 */
class ConfirmImportRequest extends FormRequest
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
            'convert_to_opportunity' => ['nullable', 'boolean'],
        ];
    }
}
