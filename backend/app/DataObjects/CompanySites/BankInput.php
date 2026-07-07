<?php

namespace App\DataObjects\CompanySites;

/**
 * A single bank row inside a company site write (spec 0020).
 *
 * Couples the optional client-supplied `id` (present → an existing row to
 * update, absent → a new row to create) with the typed payload, so
 * BankService::sync diffs by id without reading a "magic flying array" —
 * mirrors App\DataObjects\Users\ContactInput.
 */
final readonly class BankInput
{
    public function __construct(
        public ?int $id,
        public CreateBank $data,
    ) {}
}
