<?php

namespace App\DataObjects\Roles;

/**
 * Validated payload for creating a role (POST /api/roles).
 *
 * Declared DTO (no "magic flying array") so the StoreRoleRequest → RoleService
 * contract is explicit and the service reads typed properties — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `permissions` / `users` are null when the client did not submit the key at all
 * (leave them untouched), versus an explicit list (sync exactly those).
 */
final readonly class CreateRoleData
{
    /**
     * @param  array<int, string>|null  $permissions
     * @param  array<int, int>|null  $users  user ids to set as members
     */
    public function __construct(
        public string $name,
        public ?array $permissions = null,
        public ?array $users = null,
    ) {}

    /**
     * Build from the validated StoreRoleRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            permissions: array_key_exists('permissions', $data) ? (array) $data['permissions'] : null,
            users: array_key_exists('users', $data)
                ? array_map('intval', (array) $data['users'])
                : null,
        );
    }

    public function hasPermissions(): bool
    {
        return $this->permissions !== null;
    }

    public function hasUsers(): bool
    {
        return $this->users !== null;
    }
}
