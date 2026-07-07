<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAttributeValue>
 */
class ProductAttributeValueFactory extends Factory
{
    protected $model = ProductAttributeValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'attribute_id' => Attribute::factory(),
            'value_string' => fake()->word(),
        ];
    }

    public function integer(int $value): static
    {
        return $this->state(fn (): array => ['value_string' => null, 'value_integer' => $value]);
    }

    public function decimal(float $value): static
    {
        return $this->state(fn (): array => ['value_string' => null, 'value_decimal' => $value]);
    }

    public function boolean(bool $value): static
    {
        return $this->state(fn (): array => ['value_string' => null, 'value_boolean' => $value]);
    }

    public function option(int $optionId): static
    {
        return $this->state(fn (): array => ['value_string' => null, 'option_id' => $optionId]);
    }
}
