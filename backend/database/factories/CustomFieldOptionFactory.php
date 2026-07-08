<?php

namespace Database\Factories;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldOption>
 */
class CustomFieldOptionFactory extends Factory
{
    protected $model = CustomFieldOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'definition_id' => CustomFieldDefinition::factory()->ofType('enum'),
            'value' => fake()->unique()->slug(1),
            'label' => fake()->words(2, true),
            'sort_order' => 0,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
