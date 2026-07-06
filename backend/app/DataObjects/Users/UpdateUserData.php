<?php

namespace App\DataObjects\Users;

/**
 * Validated payload for a partial (PATCH) user update (PUT/PATCH /api/users/{user}).
 *
 * Declared DTO (no "magic flying array") so the UpdateUserRequest → UserService
 * contract is explicit. A null property means the client did NOT submit that key
 * (leave it untouched), preserving the partial-update semantics that the service
 * previously expressed with `array_key_exists()` on a raw array — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `roles` distinguishes "not submitted" (null → leave roles untouched) from an
 * explicit empty list ([] → remove every role).
 *
 * `name` is intentionally absent: the client no longer supplies it. When a
 * personal-data card is submitted the UserService re-derives `users.name` from it
 * (ADR 0012, CreatePersonalData::displayName()); otherwise the name is untouched.
 */
final readonly class UpdateUserData
{
    /**
     * @param  array<int, string>|null  $roles
     */
    public function __construct(
        public ?string $email = null,
        public ?string $locale = null,
        public ?string $password = null,
        public ?bool $is_active = null,
        public ?array $roles = null,
    ) {}

    /**
     * Build from the validated UpdateUserRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            email: array_key_exists('email', $data) ? (string) $data['email'] : null,
            locale: array_key_exists('locale', $data) ? (string) $data['locale'] : null,
            password: array_key_exists('password', $data) ? (string) $data['password'] : null,
            is_active: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            roles: array_key_exists('roles', $data) ? (array) $data['roles'] : null,
        );
    }

    public function hasRoles(): bool
    {
        return $this->roles !== null;
    }

    /**
     * Only the account attributes the client actually submitted, ready for a
     * partial mass-assignment update (framework array boundary). Roles are handled
     * separately, and `name` is derived from the card by the UserService (ADR 0012),
     * not submitted here.
     *
     * @return array<string, string|bool>
     */
    public function submittedAttributes(): array
    {
        return array_filter(
            [
                'email' => $this->email,
                'locale' => $this->locale,
                'password' => $this->password,
                'is_active' => $this->is_active,
            ],
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
