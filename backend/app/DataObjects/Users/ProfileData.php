<?php

namespace App\DataObjects\Users;

use App\DataObjects\PersonalData\CreatePersonalData;

/**
 * The nested personal-data profile submitted alongside a user write (ADR 0012):
 * the card plus its optional contacts/addresses collections.
 *
 * Collection semantics mirror the wire contract: a null collection means the
 * client did NOT send that key (leave it untouched), while a present collection
 * (possibly empty) is authoritative and is synced as-is (empty array → delete all
 * owned children). The whole DTO is null at the request boundary when
 * `personal_data` itself is absent — see standards/architecture.md → Data
 * Transfer Objects.
 */
final readonly class ProfileData
{
    /**
     * @param  array<int, ContactInput>|null  $contacts
     * @param  array<int, AddressInput>|null  $addresses
     */
    public function __construct(
        public CreatePersonalData $card,
        public ?array $contacts = null,
        public ?array $addresses = null,
    ) {}
}
