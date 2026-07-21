<?php

namespace App\Http\Requests\Attachments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/attachments — list the files owned by one
 * polymorphic owner (optionally narrowed to a named `collection`).
 *
 * Both `attachable_type` and `attachable_id` are required: there is no
 * "list everything" mode. The owner alias is constrained by the same
 * config/attachments.php allowlist used on upload.
 */
class IndexAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the AttachmentPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string', Rule::in($this->allowedAliases())],
            'attachable_id' => ['required', 'integer', 'min:1'],
            'collection' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Alias => model class allowlist (config/attachments.php).
     *
     * @return array<int, string>
     */
    private function allowedAliases(): array
    {
        return array_keys((array) config('attachments.attachable_types'));
    }
}
