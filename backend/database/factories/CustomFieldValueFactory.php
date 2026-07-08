<?php

namespace Database\Factories;

use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldValue>
 */
class CustomFieldValueFactory extends Factory
{
    protected $model = CustomFieldValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => 'companies',
            'entity_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'values' => [],
        ];
    }

    public function forEntity(string $entityType, int $entityId): static
    {
        return $this->state(fn (): array => ['entity_type' => $entityType, 'entity_id' => $entityId]);
    }
}
