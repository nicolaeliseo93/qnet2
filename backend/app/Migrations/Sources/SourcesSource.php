<?php

namespace App\Migrations\Sources;

use App\DataObjects\Sources\CreateSourceData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Source;
use App\Services\SourceService;
use RuntimeException;

/**
 * `sources` migration source (spec 0013 / 0018): a plain lookup entity
 * (id, name) created through SourceService, mirroring ReferentTypesSource.
 * An independent phase-1 anchor: registry records reference their source via
 * `old_id` once that relation exists (spec 0018 scope). Re-import is
 * idempotent (skip by old_id); the name is NOT unique, so no adoption logic.
 */
class SourcesSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly SourceService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'sources';
    }

    public function label(): string
    {
        return 'Sources';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
        ];
    }

    public function endpoint(): string
    {
        return 'sources';
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
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(Source::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $source = $this->service->create(new CreateSourceData(name: $name));

        $source->old_id = $externalId;
        $source->save();

        return MigrationRowOutcome::created(model: $source);
    }
}
