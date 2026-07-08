<?php

namespace App\Migrations\Sources;

use App\DataObjects\EaSectors\CreateEaSectorData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\EaSector;
use App\Services\EaSectorService;
use RuntimeException;

/**
 * `ea-sectors` migration source (spec 0013 / 0018): a SELF-referential tree
 * (id, name, parent_id) created through EaSectorService. `parent_id` is an
 * EXTERNAL id remapped to the qnet parent via `old_id`. A child whose parent
 * has not been migrated yet (parent later in the same external listing) is
 * created detached with a non-fatal warning, then relinked in a second pass
 * (afterImport) once every node exists. Re-import is idempotent (skip by
 * old_id); the name is NOT unique.
 */
class EaSectorsSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly EaSectorService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'ea-sectors';
    }

    public function label(): string
    {
        return 'EA sectors';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    public function columns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'parent_id', 'label' => 'Parent (external id)', 'type' => 'number'],
        ];
    }

    public function endpoint(): string
    {
        return 'ea-sectors';
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
            'name' => $record['name'] ?? null,
            'parent_id' => $record['parent_id'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(EaSector::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $warnings = [];
        $parentId = $this->resolveParent($record['parent_id'] ?? null, $warnings);

        $sector = $this->service->create(new CreateEaSectorData(name: $name, parentId: $parentId));

        $sector->old_id = $externalId;
        $sector->save();

        return MigrationRowOutcome::created($warnings);
    }

    /**
     * Second pass: relink every sector that was created detached because its
     * parent had not been migrated yet. Now that all nodes exist, resolve the
     * external parent via `old_id` and set it where it is still null. Leaves an
     * already-linked or genuinely-rootless sector untouched (idempotent).
     */
    protected function afterImport(MigrationImportContext $context): void
    {
        $this->eachRecord(function (array $record): void {
            $externalParent = $record['parent_id'] ?? null;

            if ($externalParent === null || $externalParent === '') {
                return;
            }

            $sector = EaSector::query()
                ->where('old_id', $this->externalId($record))
                ->whereNull('parent_id')
                ->first();

            $parentId = $sector === null ? null : $this->resolveOldId(EaSector::class, $externalParent);

            if ($sector !== null && $parentId !== null) {
                $sector->update(['parent_id' => $parentId]);
            }
        });
    }

    /**
     * Remap the external parent reference to the qnet parent id via `old_id`.
     * Absent/blank → null (a root sector, no warning); a reference that
     * resolves to no migrated parent → non-fatal warning, the sector is created
     * detached and relinked later by afterImport() if the parent then exists.
     *
     * @param  array<int, string>  $warnings
     */
    private function resolveParent(mixed $externalRef, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '') {
            return null;
        }

        $id = $this->resolveOldId(EaSector::class, $externalRef);

        if ($id === null) {
            $warnings[] = "Unresolved parent_id (external id {$externalRef}); sector created detached, relinked if the parent is migrated.";
        }

        return $id;
    }
}
