<?php

namespace App\Migrations\Sources;

use App\DataObjects\Attributes\CreateAttributeData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Attribute;
use App\Services\AttributeService;
use RuntimeException;

/**
 * `attributes` migration source (spec 0013 / 0017): the global attribute
 * catalogue (id, code, name, data_type) created through AttributeService. A
 * phase-1 anchor: the attribute owns its ENUM option list (attribute_options),
 * imported in the same row via the CreateAttributeData `options` payload — an
 * ENUM attribute without options is rejected by the Service and isolated as a
 * failed row. The category/attribute pivot (attribute_category) is NOT
 * carried here (mirrors TagsSource: the import creates only the entity itself).
 * Re-import is idempotent (skip by old_id); `code` is unique.
 */
class AttributesSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly AttributeService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'attributes';
    }

    public function label(): string
    {
        return 'Attributes';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'code', 'label' => 'Code', 'type' => 'string'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'data_type', 'label' => 'Data type', 'type' => 'string'],
        ];
    }

    public function endpoint(): string
    {
        return 'attributes';
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
            'code' => $record['code'] ?? null,
            'name' => $record['name'] ?? null,
            'data_type' => $record['data_type'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(Attribute::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $code = trim((string) ($record['code'] ?? ''));

        if ($code === '') {
            throw new RuntimeException('code is required.');
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $dataType = trim((string) ($record['data_type'] ?? ''));

        if ($dataType === '') {
            throw new RuntimeException('data_type is required.');
        }

        $attribute = $this->service->create(new CreateAttributeData(
            code: $code,
            name: $name,
            dataType: $dataType,
            options: $this->mapOptions($record),
        ));

        $attribute->old_id = $externalId;
        $attribute->save();

        return MigrationRowOutcome::created(model: $attribute);
    }

    /**
     * Map the external ENUM option list into the shape CreateAttributeData
     * expects. Returns null when no options were provided (a non-ENUM
     * attribute); an ENUM attribute that arrives without options is rejected by
     * AttributeService (422) and isolated as a failed row.
     *
     * @param  array<string, mixed>  $record
     * @return array<int, array{value: string, label: string, sort_order: int}>|null
     */
    private function mapOptions(array $record): ?array
    {
        $raw = $record['options'] ?? null;

        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $options = [];

        foreach ($raw as $index => $option) {
            if (! is_array($option)) {
                continue;
            }

            $value = trim((string) ($option['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            $label = trim((string) ($option['label'] ?? ''));

            $options[] = [
                'value' => $value,
                'label' => $label !== '' ? $label : $value,
                'sort_order' => (int) ($option['sort_order'] ?? $index),
            ];
        }

        return $options === [] ? null : $options;
    }
}
