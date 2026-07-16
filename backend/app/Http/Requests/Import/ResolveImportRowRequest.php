<?php

namespace App\Http\Requests\Import;

use App\Enums\ImportRowResolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /api/imports/{domain}/{importRun}/rows/{row}/resolution
 * (spec 0036): the operator's per-row duplicate decision. Only the
 * `resolution` enum value is validated here — ownership, the `reviewing`
 * status guard and the row's own `duplicate` status are NOT handled here,
 * mirroring UpdateImportRowRequest's split with the controller/Service.
 */
class ResolveImportRowRequest extends FormRequest
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
            'resolution' => ['required', Rule::enum(ImportRowResolution::class)],
        ];
    }
}
