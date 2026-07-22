<?php

namespace App\Http\Requests\Notes\Concerns;

use App\Notes\NoteEntityRegistry;
use Illuminate\Validation\Rule;

/**
 * Shared `entity_type`/`entity_id` rules for every note endpoint that takes
 * them (index, store, mentionable-users): `entity_type` must be a slug
 * registered in config/notes.php — the allow-list boundary (spec 0052, D-9)
 * — checked BEFORE any query touches the target model (AC-022). `entity_id`
 * existence is intentionally NOT validated here: a missing record is a 404
 * from the Service (Model::findOrFail), not a 422.
 */
trait ValidatesNotableEntity
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function notableEntityRules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::in(app(NoteEntityRegistry::class)->registeredTypes())],
            'entity_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function entityType(): string
    {
        return (string) $this->validated('entity_type');
    }

    public function entityId(): int
    {
        return (int) $this->validated('entity_id');
    }
}
