<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'parent_id' => null,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function childOf(ProductCategory $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }
}
