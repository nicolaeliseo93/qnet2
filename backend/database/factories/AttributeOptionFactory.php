<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeOption>
 */
class AttributeOptionFactory extends Factory
{
    protected $model = AttributeOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attribute_id' => Attribute::factory(),
            'value' => fake()->unique()->slug(2, false),
            'label' => fake()->unique()->word(),
            'sort_order' => 0,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
