<?php

namespace App\DataObjects\CompanySites;

/**
 * Declared payload for creating/updating a CompanySiteBank row (see
 * standards/architecture.md → Data Transfer Objects). Built at the HTTP
 * boundary by the FormRequest and consumed by BankService.
 */
final readonly class CreateBank
{
    public function __construct(
        public string $name,
        public ?string $iban = null,
        public ?string $notes = null,
    ) {}

    /**
     * The attributes ready for mass assignment on the CompanySiteBank model.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'iban' => $this->iban,
            'notes' => $this->notes,
        ];
    }
}
