<?php

namespace App\Services;

use App\DataObjects\Products\CreateProductData;
use App\DataObjects\Products\UpdateProductData;
use App\Models\Product;
use App\Services\ProductCategories\CategoryHierarchy;

/**
 * Business logic for the `products` resource (spec 0017): create/update
 * (generic fields only — the category-driven `attributes` catalogue is a
 * reusable template, never coupled to a product's own values) and delete.
 * The controller stays thin; this Service is the single authority.
 */
class ProductService
{
    /**
     * Relations eager-loaded on every returned model, so ProductResource
     * never N+1s while hydrating the category summary.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = ['category'];

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The product's category's EFFECTIVE business function (spec 0023),
     * read-only: `id`/`name` only — the category's own `inherited`/
     * `source_category` detail is a product-categories-only concern. Null
     * when the product has no category, or the category (and its ancestry)
     * has none.
     *
     * @return array{id: int, name: string}|null
     */
    public function effectiveBusinessFunction(Product $product): ?array
    {
        if ($product->category === null) {
            return null;
        }

        $effective = $this->hierarchy->effectiveBusinessFunction($product->category);

        if ($effective === null) {
            return null;
        }

        return ['id' => $effective['id'], 'name' => $effective['name']];
    }

    public function create(CreateProductData $data): Product
    {
        /** @var Product $product */
        $product = Product::create([
            'name' => $data->name,
            'description' => $data->description,
            'cost' => $data->cost,
            'price' => $data->price,
            'category_id' => $data->categoryId,
            'product_type' => $data->productType,
        ]);

        return $product->fresh(self::HYDRATED_RELATIONS);
    }

    public function update(Product $product, UpdateProductData $data): Product
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $product->fill($attributes)->save();

        return $product->fresh(self::HYDRATED_RELATIONS);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
