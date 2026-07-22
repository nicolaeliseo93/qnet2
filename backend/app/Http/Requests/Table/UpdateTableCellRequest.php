<?php

namespace App\Http\Requests\Table;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PATCH /api/tables/{domain}/rows/{row} (spec
 * 0053): only the request SHAPE — `column` is a non-empty string, `value` is
 * whatever scalar/null the client sends. Whether `column` is actually
 * editable for this actor/row, and whether `value` satisfies that column's
 * own derived rules, are guards TableCellUpdateService enforces against the
 * resolved TableDefinition and the REAL row — never here, mirroring every
 * other Table FormRequest's convention (authorization/whitelisting stay out
 * of the FormRequest so a malformed shape never masks the real 403/422/404).
 */
class UpdateTableCellRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller/service against the
        // resolved TableDefinition and the real row.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'column' => ['required', 'string'],
            'value' => ['sometimes'],
        ];
    }
}
