<?php

namespace App\DataObjects\Notes;

/**
 * Validated payload for PATCH /api/notes/{note} (spec 0052). `entity_type`/
 * `entity_id`/`parent_id` are not modifiable (data_contract) so they carry
 * no representation here.
 */
final readonly class UpdateNoteData
{
    /**
     * @param  array<int, int>  $mentionIds
     */
    public function __construct(
        public string $body,
        public array $mentionIds,
    ) {}
}
