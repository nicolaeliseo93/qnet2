<?php

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2, false),
            'name' => fake()->unique()->words(2, true),
            'data_type' => AttributeType::String,
        ];
    }

    public function integer(): static
    {
        return $this->state(fn (): array => ['data_type' => AttributeType::Integer]);
    }

    public function decimal(): static
    {
        return $this->state(fn (): array => ['data_type' => AttributeType::Decimal]);
    }

    public function boolean(): static
    {
        return $this->state(fn (): array => ['data_type' => AttributeType::Boolean]);
    }

    /**
     * ENUM-typed, with $count options attached after creation.
     */
    public function enum(int $count = 3): static
    {
        return $this->state(fn (): array => ['data_type' => AttributeType::Enum])
            ->afterCreating(function (Attribute $attribute) use ($count): void {
                AttributeOption::factory()
                    ->count($count)
                    ->for($attribute)
                    ->create();
            });
    }
}
