<?php

namespace App\Imports;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\CreateUserData;
use App\DataObjects\Users\ProfileData;
use App\Enums\LocaleEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Str;

/**
 * Import definition for `users`.
 *
 * Columns: `email` (required, natural key), `type` (optional
 * individual|company, blank -> individual), `first_name`/`last_name`
 * (required when `type` is individual), `company_name` (required when `type`
 * is company), `locale` (optional -> LocaleEnum::default()), `roles`
 * (optional, PIPE-delimited `|` list of role NAMES — same delimiter
 * convention as RolesImportDefinition's `permissions` cell).
 *
 * SECURITY: every role name must both EXIST and be ASSIGNABLE BY THE ACTOR
 * (UserService::assignableRoleNames() — the exact privilege-escalation guard
 * StoreUserRequest/UserService::create() enforce: a non-super-admin can never
 * assign `super-admin`). Unlike the real endpoint, which silently drops a
 * non-assignable role from the sync, an import row requesting one is REJECTED
 * OUTRIGHT (validateRow() error) — no partially-escalated user is ever
 * created from a CSV row.
 *
 * PASSWORD: the CSV never carries a password. Each created user gets a random,
 * strong, one-off password (Str::password()) that is never stored in the
 * report/preview/logs; the user sets their own via the existing forgot-
 * password flow. The User model's `hashed` cast hashes it on write.
 *
 * Row creation delegates entirely to UserService::create() (which itself
 * delegates the personal-data card + `users.name` derivation to
 * ProfileWriter) — no duplicated logic.
 */
class UsersImportDefinition extends AbstractImportDefinition
{
    private const int PASSWORD_LENGTH = 32;

    public function __construct(private readonly UserService $service) {}

    public function domain(): string
    {
        return 'users';
    }

    public function modelClass(): string
    {
        return User::class;
    }

    public function columns(): array
    {
        return [
            ['id' => 'email', 'required' => true],
            ['id' => 'type', 'required' => false],
            ['id' => 'first_name', 'required' => false],
            ['id' => 'last_name', 'required' => false],
            ['id' => 'company_name', 'required' => false],
            ['id' => 'locale', 'required' => false],
            ['id' => 'roles', 'required' => false],
        ];
    }

    public function validateRow(array $row, ImportRowContext $context): array
    {
        return [
            ...$this->emailErrors($row),
            ...$this->typeErrors($row),
            ...$this->localeErrors($row),
            ...$this->roleErrors($row, $context->actor),
        ];
    }

    public function dedupKey(array $row): ?string
    {
        $email = trim($row['email'] ?? '');

        return $email === '' ? null : mb_strtolower($email);
    }

    /**
     * Fetches only the `email` column and compares in PHP (no raw SQL), same
     * trade-off as GeoResolver/the other definitions.
     */
    public function existsInDatabase(string $key): bool
    {
        return User::query()
            ->get(['email'])
            ->contains(static fn (User $user): bool => mb_strtolower($user->email) === $key);
    }

    public function createRow(User $actor, array $row): void
    {
        $type = $this->resolveType($row);
        $locale = trim($row['locale'] ?? '');
        $roles = $this->splitPipeList($row['roles'] ?? null);

        $card = new CreatePersonalData(
            type: $type,
            firstName: $type === PersonalDataTypeEnum::Individual ? trim($row['first_name'] ?? '') : null,
            lastName: $type === PersonalDataTypeEnum::Individual ? trim($row['last_name'] ?? '') : null,
            companyName: $type === PersonalDataTypeEnum::Company ? trim($row['company_name'] ?? '') : null,
        );

        $this->service->create(
            $actor,
            new CreateUserData(
                email: trim($row['email']),
                locale: $locale !== '' ? $locale : (LocaleEnum::default() ?? LocaleEnum::En)->value,
                password: Str::password(self::PASSWORD_LENGTH),
                roles: $roles !== [] ? $roles : null,
            ),
            new ProfileData(card: $card),
        );
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function emailErrors(array $row): array
    {
        $email = trim($row['email'] ?? '');

        if ($email === '') {
            return ['email is required.'];
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? ['email is not a valid email address.'] : [];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function typeErrors(array $row): array
    {
        $rawType = trim($row['type'] ?? '');

        if ($rawType !== '' && PersonalDataTypeEnum::tryFrom($rawType) === null) {
            return ['type must be individual, company, or blank.'];
        }

        $type = $this->resolveType($row);
        $errors = [];

        if ($type === PersonalDataTypeEnum::Individual) {
            if (trim($row['first_name'] ?? '') === '') {
                $errors[] = 'first_name is required when type is individual.';
            }
            if (trim($row['last_name'] ?? '') === '') {
                $errors[] = 'last_name is required when type is individual.';
            }
        } else {
            if (trim($row['company_name'] ?? '') === '') {
                $errors[] = 'company_name is required when type is company.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function localeErrors(array $row): array
    {
        $locale = trim($row['locale'] ?? '');

        if ($locale !== '' && ! in_array($locale, LocaleEnum::values(), true)) {
            return ['locale must be one of: '.implode(', ', LocaleEnum::values()).', or blank.'];
        }

        return [];
    }

    /**
     * Every requested role must EXIST and be ASSIGNABLE BY THE ACTOR — the
     * same privilege-escalation guard the real endpoint enforces
     * (UserService::assignableRoleNames() -> RoleAssignmentGuard). A row is
     * rejected outright rather than silently dropping the role.
     *
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function roleErrors(array $row, User $actor): array
    {
        $names = $this->splitPipeList($row['roles'] ?? null);

        if ($names === []) {
            return [];
        }

        $existing = Role::query()->whereIn('name', $names)->pluck('name')->all();
        $errors = array_map(
            static fn (string $name): string => "Unknown role: {$name}.",
            array_values(array_diff($names, $existing)),
        );

        $assignable = $this->service->assignableRoleNames($actor);
        $notAssignable = array_diff(array_intersect($names, $existing), $assignable);

        foreach ($notAssignable as $name) {
            $errors[] = "Role not assignable by the importing user: {$name}.";
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveType(array $row): PersonalDataTypeEnum
    {
        $raw = trim($row['type'] ?? '');

        return $raw === '' ? PersonalDataTypeEnum::Individual : PersonalDataTypeEnum::fromValue($raw);
    }
}
