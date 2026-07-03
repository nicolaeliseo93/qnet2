<?php

namespace App\DataObjects\BusinessFunctions;

/**
 * Validated payload for creating a business function (POST /api/business-functions).
 *
 * Declared DTO (no "magic flying array") so the StoreBusinessFunctionRequest →
 * BusinessFunctionService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `type` is the single client-facing mutually-exclusive selector; the Service
 * maps it onto the two `is_business_unit`/`is_business_service` columns.
 * `users` is null when the client did not submit the key at all (nothing to
 * sync — a brand new function starts with no associated users anyway).
 */
final readonly class CreateBusinessFunctionData
{
    /**
     * @param  array<int, int>|null  $users
     */
    public function __construct(
        public string $name,
        public ?string $type = null,
        public ?int $managerId = null,
        public ?array $users = null,
    ) {}

    /**
     * Build from the validated StoreBusinessFunctionRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            type: array_key_exists('type', $data) ? $data['type'] : null,
            managerId: array_key_exists('manager_id', $data) && $data['manager_id'] !== null
                ? (int) $data['manager_id']
                : null,
            users: array_key_exists('users', $data) ? array_map('intval', (array) $data['users']) : null,
        );
    }

    public function hasUsers(): bool
    {
        return $this->users !== null;
    }
}
