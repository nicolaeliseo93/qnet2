<?php

namespace App\Migrations\Sources;

use App\DataObjects\Tags\CreateTagData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Tag;
use App\Services\TagService;
use RuntimeException;

/**
 * `tags` migration source (spec 0013 / 0019): a plain lookup entity
 * (id, name) created through TagService, mirroring SourcesSource. An
 * independent phase-1 anchor: a Tag is a reusable classification attached to
 * other entities via the polymorphic `taggables` pivot (spec 0019), but the
 * import creates only the Tag itself (no association is carried here).
 * Re-import is idempotent (skip by old_id); the name is NOT unique.
 */
class TagsSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly TagService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'tags';
    }

    public function label(): string
    {
        return 'Tags';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    public function columns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
        ];
    }

    public function endpoint(): string
    {
        return 'tags';
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
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(Tag::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $tag = $this->service->create(new CreateTagData(name: $name));

        $tag->old_id = $externalId;
        $tag->save();

        return MigrationRowOutcome::created();
    }
}
