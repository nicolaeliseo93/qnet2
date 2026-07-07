<?php

namespace Database\Factories;

use App\Models\EaSector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EaSector>
 */
class EaSectorFactory extends Factory
{
    protected $model = EaSector::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'parent_id' => null,
        ];
    }

    public function childOf(EaSector $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }
}
