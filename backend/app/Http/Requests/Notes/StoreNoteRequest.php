<?php

namespace App\Http\Requests\Notes;

use App\DataObjects\Notes\CreateNoteData;
use App\Http\Requests\Notes\Concerns\ValidatesNotableEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/notes (spec 0052 data_contract): the host entity
 * (ValidatesNotableEntity), the body, an optional parent and the raw
 * mentions array.
 *
 * `parent_id` only checks the note exists (not soft-deleted): the D-7
 * single-level normalization and the "different host record" 422 both need
 * the resolved host record, so they run in NoteThreadResolver, not here.
 * Mention/body coherence (D-12) and the D-10 mentionable-set boundary run in
 * MentionValidator for the SAME reason.
 *
 * Authorization is NOT handled here: `notes.create` is checked in the
 * controller via NotePolicy, the AND'd read access to the host record inside
 * NoteService::create (D-6).
 */
class StoreNoteRequest extends FormRequest
{
    use ValidatesNotableEntity;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->notableEntityRules(), [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('notes', 'id')->whereNull('deleted_at')],
            'mentions' => ['sometimes', 'array'],
            'mentions.*' => ['integer'],
        ]);
    }

    public function toData(): CreateNoteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new CreateNoteData(
            entityType: (string) $validated['entity_type'],
            entityId: (int) $validated['entity_id'],
            body: (string) $validated['body'],
            parentId: isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            mentionIds: array_map('intval', $validated['mentions'] ?? []),
        );
    }
}
