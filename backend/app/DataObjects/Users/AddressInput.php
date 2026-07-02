<?php

namespace App\DataObjects\Users;

use App\DataObjects\PersonalData\CreateAddress;

/**
 * A single address row inside a nested user-profile write (see ADR 0012).
 *
 * Couples the optional client-supplied `id` (present → an existing row to update,
 * absent → a new row to create) with the typed address payload, so the sync
 * service diffs by id without reading a "magic flying array" — see
 * standards/architecture.md → Data Transfer Objects.
 */
final readonly class AddressInput
{
    public function __construct(
        public ?int $id,
        public CreateAddress $data,
    ) {}
}
