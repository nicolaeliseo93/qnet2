<?php

namespace App\Http\Requests\Notes;

use App\DataObjects\Notes\UpdateNoteData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PATCH /api/notes/{note} (spec 0052 data_contract): only `body`
 * and `mentions` are writable — `entity_type`/`entity_id`/`parent_id` are
 * not modifiable and simply ignored if sent.
 *
 * Authorization is NOT handled here: ownership (D-8, `notes.user_id ===
 * auth id`) is checked in the controller via NotePolicy::update, and the
 * "still readable" re-check runs inside NoteService::update.
 */
class UpdateNoteRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'mentions' => ['sometimes', 'array'],
            'mentions.*' => ['integer'],
        ];
    }

    public function toData(): UpdateNoteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new UpdateNoteData(
            body: (string) $validated['body'],
            mentionIds: array_map('intval', $validated['mentions'] ?? []),
        );
    }
}
