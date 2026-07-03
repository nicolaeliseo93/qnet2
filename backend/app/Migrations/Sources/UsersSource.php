<?php

namespace App\Migrations\Sources;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\CreateUserData;
use App\DataObjects\Users\ProfileData;
use App\Enums\PersonalDataTypeEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * `users` migration source (spec 0013 AC-008/009): creates the account plus
 * its personal-data card via UserService::create (single derivation point
 * for `users.name`), then remaps the external `role_ids` to qnet roles via
 * `old_id`. An unresolved role reference is a non-fatal warning — the user
 * is still created without that role. The password is never provided by the
 * external system (out of scope): a random one is generated so the migrated
 * account only becomes usable through the normal forgot-password flow.
 */
class UsersSource extends AbstractMigrationSource
{
    private const string DEFAULT_LOCALE = 'en';

    public function __construct(
        ExternalApiClient $client,
        private readonly UserService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    public function columns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'email', 'label' => 'Email', 'type' => 'string'],
            ['id' => 'first_name', 'label' => 'First name', 'type' => 'string'],
            ['id' => 'last_name', 'label' => 'Last name', 'type' => 'string'],
            ['id' => 'locale', 'label' => 'Locale', 'type' => 'string'],
        ];
    }

    protected function endpoint(): string
    {
        return 'users';
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'email' => $record['email'] ?? null,
            'first_name' => $record['first_name'] ?? null,
            'last_name' => $record['last_name'] ?? null,
            'locale' => $record['locale'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(User::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $email = trim((string) ($record['email'] ?? ''));

        if ($email === '') {
            throw new RuntimeException('email is required.');
        }

        [$roleNames, $warnings] = $this->resolveRoleNames((array) ($record['role_ids'] ?? []));

        $user = $this->service->create(
            $context->actor,
            new CreateUserData(
                email: $email,
                locale: (string) ($record['locale'] ?? self::DEFAULT_LOCALE),
                password: Str::password(24),
                roles: $roleNames === [] ? null : $roleNames,
            ),
            new ProfileData(card: new CreatePersonalData(
                type: PersonalDataTypeEnum::Individual,
                firstName: (string) ($record['first_name'] ?? ''),
                lastName: (string) ($record['last_name'] ?? ''),
            )),
        );

        $user->old_id = $externalId;
        $user->save();

        return MigrationRowOutcome::created($warnings);
    }

    /**
     * Remap the external role references to qnet role names via `old_id`. A
     * reference that resolves to no migrated role becomes a non-fatal
     * warning (the user is still created, just without that role).
     *
     * @param  array<int, int|string>  $externalRoleIds
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function resolveRoleNames(array $externalRoleIds): array
    {
        $names = [];
        $warnings = [];

        foreach ($externalRoleIds as $externalRoleId) {
            $roleId = $this->resolveOldId(Role::class, $externalRoleId);

            if ($roleId === null) {
                $warnings[] = "Unresolved role reference (external id {$externalRoleId}).";

                continue;
            }

            /** @var Role|null $role */
            $role = Role::query()->find($roleId);

            if ($role !== null) {
                $names[] = $role->name;
            }
        }

        return [$names, $warnings];
    }
}
