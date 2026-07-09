<?php

namespace App\Migrations\Sources;

use App\DataObjects\Roles\CreateRoleData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Role;
use App\Services\RoleService;
use RuntimeException;

/**
 * `roles` migration source (spec 0013 AC-011): imports `name` + `description`
 * (+ `old_id`) — the role's permissions are NOT imported (qnet's abilities are
 * generated from code via `permissions:sync`, never arbitrary). When the
 * external `name` already matches an existing qnet role that has no
 * `old_id` yet, the import ADOPTS the old_id onto it instead of creating a
 * duplicate (roles.name is unique) and backfills its description only when the
 * existing role has none, so an adoption never clobbers a curated description.
 */
class RolesSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly RoleService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'roles';
    }

    public function label(): string
    {
        return 'Roles';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
        ];
    }

    public function endpoint(): string
    {
        return 'roles';
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'name' => $record['name'] ?? null,
            'description' => $record['description'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $description = $this->blankToNull($record['description'] ?? null);

        if ($this->existsByOldId(Role::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $adopted = Role::query()->where('name', $name)->whereNull('old_id')->first();

        if ($adopted !== null) {
            $adopted->old_id = $externalId;
            // Backfill only an empty description: never clobber a curated one.
            if ($description !== null && $this->blankToNull($adopted->description) === null) {
                $adopted->description = $description;
            }
            $adopted->save();

            return MigrationRowOutcome::created(model: $adopted);
        }

        $role = $this->service->create($context->actor, new CreateRoleData(name: $name, description: $description));
        $role->old_id = $externalId;
        $role->save();

        return MigrationRowOutcome::created(model: $role);
    }

    private function blankToNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
