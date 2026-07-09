<?php

namespace Database\Seeders;

use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldWriter;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Provision a full set of demo custom fields (spec 0021) on EVERY
 * custom-fieldable entity, then populate them on existing demo rows — so the
 * whole universal-custom-fields stack (admin definitions, grid columns, meta,
 * forms, JSON value read/write) can be eyeballed end-to-end across all modules.
 *
 * Demo/fake data only: runs LAST in DemoDataSeeder (needs every entity's rows
 * to already exist, and companies for the relation target). Idempotent —
 * updateOrCreate on the natural keys, so re-runs never duplicate.
 */
class DemoCustomFieldSeeder extends Seeder
{
    /**
     * Entity types skipped: `custom-fields` is the definitions module itself
     * (custom fields on custom fields is meta-recursive noise, not a demo).
     *
     * @var list<string>
     */
    private const array EXCLUDED_ENTITIES = ['custom-fields'];

    /**
     * One definition per scalar MVP type, created for every entity. The enum
     * (with options) and the relation field are appended separately below,
     * so all 7 handler types are exercised.
     *
     * @var list<array{key: string, type: string, label: string}>
     */
    private const array SCALAR_DEFINITIONS = [
        ['key' => 'demo_note', 'type' => 'text', 'label' => 'Demo note'],
        ['key' => 'demo_description', 'type' => 'textarea', 'label' => 'Demo description'],
        ['key' => 'demo_priority', 'type' => 'integer', 'label' => 'Demo priority'],
        ['key' => 'demo_score', 'type' => 'decimal', 'label' => 'Demo score'],
        ['key' => 'demo_verified', 'type' => 'boolean', 'label' => 'Demo verified'],
    ];

    private const string ENUM_KEY = 'demo_status';

    private const string RELATION_KEY = 'demo_main_company';

    /**
     * @var list<array{value: string, label: string, is_default?: bool}>
     */
    private const array ENUM_OPTIONS = [
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'active', 'label' => 'Active', 'is_default' => true],
        ['value' => 'archived', 'label' => 'Archived'],
    ];

    private const int ROWS_TO_FILL = 10;

    public function __construct(
        private readonly CustomFieldEntityRegistry $registry,
        private readonly CustomFieldProvider $provider,
        private readonly CustomFieldWriter $writer,
    ) {}

    public function run(): void
    {
        // Relation demo values point at real companies; fetched once up front.
        $companyIds = Company::query()->limit(self::ROWS_TO_FILL)->pluck('id')->all();

        foreach ($this->targetEntityTypes() as $entityType) {
            // Step 1: create the definition set (scalars + enum + relation)
            $this->seedDefinitions($entityType);
            // Step 2: drop the memo so the writer's read sees the new rows
            $this->provider->forget($entityType);
            // Step 3: populate values on existing rows via the real write path
            $this->seedValues($entityType, $companyIds);
        }
    }

    /**
     * @return list<string>
     */
    private function targetEntityTypes(): array
    {
        $all = array_column($this->registry->entities(), 'entity_type');

        return array_values(array_diff($all, self::EXCLUDED_ENTITIES));
    }

    private function seedDefinitions(string $entityType): void
    {
        $sortOrder = 0;

        foreach (self::SCALAR_DEFINITIONS as $spec) {
            $this->upsertDefinition($entityType, $spec['key'], $spec['type'], $spec['label'], $sortOrder++);
        }

        $enum = $this->upsertDefinition($entityType, self::ENUM_KEY, 'enum', 'Demo status', $sortOrder++);
        $this->seedEnumOptions($enum);

        $this->upsertDefinition(
            $entityType,
            self::RELATION_KEY,
            'relation',
            'Demo main company',
            $sortOrder,
            ['relation_target' => [
                'entity_type' => 'companies',
                'cardinality' => 'one',
                'for_select_resource' => 'companies',
            ]],
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function upsertDefinition(
        string $entityType,
        string $key,
        string $type,
        string $label,
        int $sortOrder,
        array $extra = [],
    ): CustomFieldDefinition {
        return CustomFieldDefinition::updateOrCreate(
            ['entity_type' => $entityType, 'key' => $key],
            [
                'type' => $type,
                'label' => $label,
                'sort_order' => $sortOrder,
                'is_indexed' => false,
                'is_active' => true,
                ...$extra,
            ],
        );
    }

    private function seedEnumOptions(CustomFieldDefinition $definition): void
    {
        $sortOrder = 0;

        foreach (self::ENUM_OPTIONS as $option) {
            CustomFieldOption::updateOrCreate(
                ['definition_id' => $definition->id, 'value' => $option['value']],
                [
                    'label' => $option['label'],
                    'sort_order' => $sortOrder++,
                    'is_default' => $option['is_default'] ?? false,
                ],
            );
        }
    }

    /**
     * @param  list<int>  $companyIds
     */
    private function seedValues(string $entityType, array $companyIds): void
    {
        $modelClass = $this->registry->modelClassFor($entityType);

        if ($modelClass === null) {
            return;
        }

        $modelClass::query()
            ->limit(self::ROWS_TO_FILL)
            ->get()
            ->each(fn (Model $model) => $this->writer->write($model, $entityType, $this->fakeValues($companyIds)));
    }

    /**
     * @param  list<int>  $companyIds
     * @return array<string, mixed>
     */
    private function fakeValues(array $companyIds): array
    {
        $values = [
            'demo_note' => fake()->sentence(),
            'demo_description' => fake()->paragraph(),
            'demo_priority' => fake()->numberBetween(1, 100),
            'demo_score' => fake()->randomFloat(2, 0, 1000),
            'demo_verified' => fake()->boolean(),
            self::ENUM_KEY => fake()->randomElement(array_column(self::ENUM_OPTIONS, 'value')),
        ];

        if ($companyIds !== []) {
            $values[self::RELATION_KEY] = fake()->randomElement($companyIds);
        }

        return $values;
    }
}
