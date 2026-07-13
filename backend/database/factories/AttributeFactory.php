<?php

namespace Database\Factories;

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
            'type' => 'text',
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function integer(): static
    {
        return $this->state(fn (): array => ['type' => 'integer']);
    }

    public function decimal(): static
    {
        return $this->state(fn (): array => ['type' => 'decimal']);
    }

    public function boolean(): static
    {
        return $this->state(fn (): array => ['type' => 'boolean']);
    }

    /**
     * ENUM-typed, with $count options attached after creation.
     */
    public function enum(int $count = 3): static
    {
        return $this->state(fn (): array => ['type' => 'enum'])
            ->afterCreating(function (Attribute $attribute) use ($count): void {
                AttributeOption::factory()
                    ->count($count)
                    ->for($attribute)
                    ->create();
            });
    }
}
