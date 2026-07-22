<?php

namespace App\Http\Requests\Table;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PATCH /api/tables/{domain}/rows/{row} (spec
 * 0053, `note` added by spec 0054 D-5): only the request SHAPE — `column` is
 * a non-empty string, `value` is whatever scalar/null the client sends,
 * `note` an optional string. Whether `column` is actually editable for this
 * actor/row, whether `value` satisfies that column's own derived rules, and
 * whether `note` is accepted on that column at all, are guards
 * TableCellUpdateService enforces against the resolved TableDefinition and
 * the REAL row — never here, mirroring every other Table FormRequest's
 * convention (authorization/whitelisting stay out of the FormRequest so a
 * malformed shape never masks the real 403/422/404).
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
            'note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
