<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/imports/{domain} (spec 0012): a CSV/TXT file, size-
 * capped by config('imports.max_file_kb'). `extensions:` is checked ALONGSIDE
 * `mimes:` so a spoofed MIME type with a mismatched real extension is still
 * rejected (backend.md §8).
 *
 * Authorization is intentionally NOT handled here: the controller enforces
 * authorizeImport() via the ImportDefinition resolved from the {domain} route
 * segment, which this FormRequest does not see before the controller runs.
 */
class UploadImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'extensions:csv,txt',
                'max:'.(int) config('imports.max_file_kb'),
            ],
        ];
    }
}
