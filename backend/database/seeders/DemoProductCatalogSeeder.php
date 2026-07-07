<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Seed a small, illustrative product catalogue (spec 0017): a handful of
 * reusable attributes (one per data type), a two-level category tree
 * exercising attribute inheritance, and a couple of demo products with
 * typed attribute values. Idempotent: every row is looked up by its natural
 * key (attribute code / category name / product name) before being created,
 * so re-running never duplicates rows nor overwrites manual edits made
 * through the `attributes`/`product-categories`/`products` CRUD modules.
 */
class DemoProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $color = $this->attribute('color', 'Color', AttributeType::Enum, [
            ['value' => 'red', 'label' => 'Red'],
            ['value' => 'blue', 'label' => 'Blue'],
            ['value' => 'black', 'label' => 'Black'],
        ]);
        $material = $this->attribute('material', 'Material', AttributeType::String);
        $warrantyMonths = $this->attribute('warranty_months', 'Warranty (months)', AttributeType::Integer);
        $weightKg = $this->attribute('weight_kg', 'Weight (kg)', AttributeType::Decimal);
        $isWaterproof = $this->attribute('is_waterproof', 'Waterproof', AttributeType::Boolean);

        $electronics = ProductCategory::firstOrCreate(['name' => 'Electronics'], ['parent_id' => null]);
        $this->assignAttribute($electronics, $warrantyMonths, isRequired: true, sortOrder: 0);

        $laptops = ProductCategory::firstOrCreate(['name' => 'Laptops'], ['parent_id' => $electronics->id]);
        $this->assignAttribute($laptops, $weightKg, isRequired: false, sortOrder: 0);

        $clothing = ProductCategory::firstOrCreate(['name' => 'Clothing'], ['parent_id' => null]);
        $this->assignAttribute($clothing, $color, isRequired: true, sortOrder: 0);
        $this->assignAttribute($clothing, $material, isRequired: false, sortOrder: 1);

        $jackets = ProductCategory::firstOrCreate(['name' => 'Jackets'], ['parent_id' => $clothing->id]);
        $this->assignAttribute($jackets, $isWaterproof, isRequired: false, sortOrder: 0);

        $this->product($laptops, 'Demo Laptop 14"', cost: 650, price: 999, values: [
            ['attribute' => $warrantyMonths, 'column' => 'value_integer', 'value' => 24],
            ['attribute' => $weightKg, 'column' => 'value_decimal', 'value' => 1.4],
        ]);

        $blueOption = $color->options()->where('value', 'blue')->first();

        $this->product($jackets, 'Demo Rain Jacket', cost: 30, price: 79.90, values: [
            ['attribute' => $color, 'column' => 'option_id', 'value' => $blueOption?->id],
            ['attribute' => $material, 'column' => 'value_string', 'value' => 'nylon'],
            ['attribute' => $isWaterproof, 'column' => 'value_boolean', 'value' => true],
        ]);
    }

    /**
     * @param  array<int, array{value: string, label: string}>  $options
     */
    private function attribute(string $code, string $name, AttributeType $dataType, array $options = []): Attribute
    {
        /** @var Attribute $attribute */
        $attribute = Attribute::firstOrCreate(['code' => $code], ['name' => $name, 'data_type' => $dataType]);

        foreach ($options as $index => $option) {
            $attribute->options()->firstOrCreate(
                ['value' => $option['value']],
                ['label' => $option['label'], 'sort_order' => $index],
            );
        }

        return $attribute;
    }

    private function assignAttribute(ProductCategory $category, Attribute $attribute, bool $isRequired, int $sortOrder): void
    {
        $category->attributes()->syncWithoutDetaching([
            $attribute->id => ['is_required' => $isRequired, 'sort_order' => $sortOrder],
        ]);
    }

    /**
     * @param  array<int, array{attribute: Attribute, column: string, value: mixed}>  $values
     */
    private function product(ProductCategory $category, string $name, float $cost, float $price, array $values): void
    {
        /** @var Product $product */
        $product = Product::firstOrCreate(
            ['name' => $name],
            ['cost' => $cost, 'price' => $price, 'category_id' => $category->id],
        );

        foreach ($values as $row) {
            $product->attributeValues()->updateOrCreate(
                ['attribute_id' => $row['attribute']->id],
                [$row['column'] => $row['value']],
            );
        }
    }
}
