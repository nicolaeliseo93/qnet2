<?php

namespace Database\Seeders;

use App\DataObjects\Products\CreateProductData;
use App\Enums\AttributeType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductService;
use Database\Seeders\DemoProductCatalog\ProductCatalogTaxonomy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seed a rich, illustrative SERVICE catalogue (spec 0017) from the
 * declarative taxonomy in ProductCatalogTaxonomy: attributes of all 5 data
 * types (ENUM ones with their options), a two-root category tree exercising
 * multi-level inheritance AND attribute reuse (`delivery_mode` assigned to
 * both roots, never duplicated), and demo services — for leaf categories and
 * one intermediate one — each carrying a typed value for every one of its
 * EFFECTIVE attributes (own + inherited).
 *
 * Idempotent: attributes/options/categories are looked up by their natural
 * key (code/value/name) before being created, category-attribute
 * assignments are a pivot sync (never duplicates), and a product already
 * present by name is skipped entirely — re-running never duplicates rows nor
 * overwrites manual edits made through the CRUD modules.
 */
class DemoProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $attributesByCode = $this->seedAttributes();

        $this->seedTree(ProductCatalogTaxonomy::tree(), null, $attributesByCode);
    }

    /**
     * @return Collection<string, Attribute>
     */
    private function seedAttributes(): Collection
    {
        foreach (ProductCatalogTaxonomy::attributes() as $code => $definition) {
            /** @var Attribute $attribute */
            $attribute = Attribute::firstOrCreate(
                ['code' => $code],
                ['name' => $definition['name'], 'data_type' => $definition['type']],
            );

            foreach ($definition['options'] ?? [] as $index => $value) {
                $attribute->options()->firstOrCreate(['value' => $value], ['label' => $value, 'sort_order' => $index]);
            }
        }

        return Attribute::whereIn('code', array_keys(ProductCatalogTaxonomy::attributes()))->get()->keyBy('code');
    }

    /**
     * Recursively walk the taxonomy tree: create/reuse the category, sync
     * its OWN attribute assignments, seed its demo products, then recurse
     * into its children.
     *
     * @param  array<string, array<string, mixed>>  $tree
     * @param  Collection<string, Attribute>  $attributesByCode
     */
    private function seedTree(array $tree, ?ProductCategory $parent, Collection $attributesByCode): void
    {
        foreach ($tree as $name => $node) {
            /** @var ProductCategory $category */
            $category = ProductCategory::firstOrCreate(['name' => $name], ['parent_id' => $parent?->id]);

            $this->assignAttributes($category, $node['attributes'] ?? [], $attributesByCode);
            $this->seedProducts($category, $node['products'] ?? [], $attributesByCode);
            $this->seedTree($node['children'] ?? [], $category, $attributesByCode);
        }
    }

    /**
     * @param  array<string, bool>  $attributes  code => is_required
     * @param  Collection<string, Attribute>  $attributesByCode
     */
    private function assignAttributes(ProductCategory $category, array $attributes, Collection $attributesByCode): void
    {
        $syncData = [];
        $sortOrder = 0;

        foreach ($attributes as $code => $isRequired) {
            $syncData[$attributesByCode[$code]->id] = ['is_required' => $isRequired, 'sort_order' => $sortOrder];
            $sortOrder++;
        }

        $category->attributes()->syncWithoutDetaching($syncData);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  Collection<string, Attribute>  $attributesByCode
     */
    private function seedProducts(ProductCategory $category, array $products, Collection $attributesByCode): void
    {
        $service = app(ProductService::class);

        foreach ($products as $definition) {
            // Natural key (name): a product already present is left untouched.
            if (Product::where('name', $definition['name'])->exists()) {
                continue;
            }

            $attributeValues = collect($definition['values'])
                ->map(fn (mixed $value, string $code): array => [
                    'attribute_id' => $attributesByCode[$code]->id,
                    'value' => $this->normalizeValue($attributesByCode[$code], $value),
                ])
                ->values()
                ->all();

            $service->create(new CreateProductData(
                name: $definition['name'],
                description: $definition['description'] ?? null,
                cost: (float) $definition['cost'],
                price: (float) $definition['price'],
                categoryId: $category->id,
                productType: ProductType::Service,
                attributes: $attributeValues,
            ));
        }
    }

    /**
     * ENUM values are already the option's `value` string (resolved to its
     * option_id by ProductAttributeValueWriter); every other type passes
     * through unchanged — the taxonomy already carries the right PHP type
     * per data_type (int/float/bool/string).
     */
    private function normalizeValue(Attribute $attribute, mixed $value): mixed
    {
        return $attribute->data_type === AttributeType::Enum ? (string) $value : $value;
    }
}
