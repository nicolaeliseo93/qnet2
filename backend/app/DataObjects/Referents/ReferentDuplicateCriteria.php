<?php

namespace App\DataObjects\Referents;

/**
 * Validated payload for POST /api/referents/duplicate-check (spec 0037):
 * the criteria to match against EXISTING referents. `contacts` entries are
 * kept as plain `{type, value}` arrays (CheckReferentDuplicatesRequest
 * already constrains `type` to email|phone|mobile) rather than typed
 * further — ReferentDuplicateFinder is their only consumer.
 */
final readonly class ReferentDuplicateCriteria
{
    /**
     * @param  array<int, array{type: string, value: string}>  $contacts
     */
    public function __construct(
        public ?string $taxCode,
        public array $contacts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            taxCode: $data['tax_code'] ?? null,
            contacts: $data['contacts'] ?? [],
        );
    }
}
