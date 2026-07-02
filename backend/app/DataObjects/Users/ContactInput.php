<?php

namespace App\DataObjects\Users;

use App\DataObjects\PersonalData\CreateContact;

/**
 * A single contact row inside a nested user-profile write (see ADR 0012).
 *
 * Couples the optional client-supplied `id` (present → an existing row to update,
 * absent → a new row to create) with the typed contact payload, so the sync
 * service diffs by id without reading a "magic flying array" — see
 * standards/architecture.md → Data Transfer Objects.
 */
final readonly class ContactInput
{
    public function __construct(
        public ?int $id,
        public CreateContact $data,
    ) {}
}
