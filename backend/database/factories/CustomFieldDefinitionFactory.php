<?php

namespace Database\Factories;

use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldDefinition>
 */
class CustomFieldDefinitionFactory extends Factory
{
    protected $model = CustomFieldDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => 'companies',
            'key' => fake()->unique()->slug(2, false),
            'type' => 'text',
            'label' => fake()->words(2, true),
            'sort_order' => 0,
            'is_indexed' => false,
            'is_active' => true,
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function forEntity(string $entityType): static
    {
        return $this->state(fn (): array => ['entity_type' => $entityType]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
