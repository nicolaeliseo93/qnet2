<?php

namespace App\DataObjects\BusinessFunctions;

/**
 * Validated payload for a partial (PATCH) business function update
 * (PUT/PATCH /api/business-functions/{businessFunction}).
 *
 * Declared DTO (no "magic flying array") so the UpdateBusinessFunctionRequest →
 * BusinessFunctionService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `type` and `manager_id` are both legitimately nullable VALUES (clearing the
 * type, removing the manager), so a plain null property cannot distinguish
 * "not submitted" from "submitted as null" the way UpdateUserData/UpdateRoleData
 * do for their non-nullable fields. The `*Submitted` flags carry that
 * distinction explicitly; `hasType()`/`hasManager()`/`hasUsers()` expose it.
 */
final readonly class UpdateBusinessFunctionData
{
    /**
     * @param  array<int, int>|null  $users
     */
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public bool $typeSubmitted = false,
        public ?int $managerId = null,
        public bool $managerSubmitted = false,
        public ?array $users = null,
    ) {}

    /**
     * Build from the validated UpdateBusinessFunctionRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            type: array_key_exists('type', $data) ? $data['type'] : null,
            typeSubmitted: array_key_exists('type', $data),
            managerId: array_key_exists('manager_id', $data) && $data['manager_id'] !== null
                ? (int) $data['manager_id']
                : null,
            managerSubmitted: array_key_exists('manager_id', $data),
            users: array_key_exists('users', $data) ? array_map('intval', (array) $data['users']) : null,
        );
    }

    public function hasType(): bool
    {
        return $this->typeSubmitted;
    }

    public function hasManager(): bool
    {
        return $this->managerSubmitted;
    }

    public function hasUsers(): bool
    {
        return $this->users !== null;
    }

    /**
     * Only the plain scalar attributes the client actually submitted, ready
     * for a partial mass-assignment update (framework array boundary). `type`
     * is handled separately by the Service (boolean remap); `users` is synced
     * separately.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->managerSubmitted) {
            $attributes['manager_id'] = $this->managerId;
        }

        return $attributes;
    }
}
