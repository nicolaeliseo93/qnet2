<?php

namespace App\DataObjects\Roles;

/**
 * Validated payload for a partial (PATCH) role update (PUT/PATCH /api/roles/{role}).
 *
 * Declared DTO (no "magic flying array") so the UpdateRoleRequest → RoleService
 * contract is explicit. A null property means the client did NOT submit that key
 * (leave it untouched), preserving partial-update semantics — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `permissions` / `users` / `fieldPermissions` distinguish "not submitted"
 * (null → leave untouched) from an explicit empty list ([] → detach every
 * permission / remove all members / clear the field-permission matrix).
 */
final readonly class UpdateRoleData
{
    /**
     * @param  array<int, string>|null  $permissions
     * @param  array<int, int>|null  $users  user ids to set as members
     * @param  array<int, array<string, mixed>>|null  $fieldPermissions  spec 0006 matrix rows
     */
    public function __construct(
        public ?string $name = null,
        public ?array $permissions = null,
        public ?array $users = null,
        public ?array $fieldPermissions = null,
    ) {}

    /**
     * Build from the validated UpdateRoleRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            permissions: array_key_exists('permissions', $data) ? (array) $data['permissions'] : null,
            users: array_key_exists('users', $data)
                ? array_map('intval', (array) $data['users'])
                : null,
            fieldPermissions: array_key_exists('field_permissions', $data) ? (array) $data['field_permissions'] : null,
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

    public function hasFieldPermissions(): bool
    {
        return $this->fieldPermissions !== null;
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update (framework array boundary). Permissions are handled
     * separately.
     *
     * @return array<string, string>
     */
    public function submittedAttributes(): array
    {
        return array_filter(
            ['name' => $this->name],
            static fn (?string $value): bool => $value !== null,
        );
    }
}
