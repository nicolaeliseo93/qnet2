<?php

namespace App\Services;

use App\DataObjects\Products\CreateProductData;
use App\DataObjects\Products\UpdateProductData;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Products\ProductAttributeValueWriter;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `products` resource (spec 0017): create/update
 * (generic fields + the dynamic attribute values, validated against the
 * target category's EFFECTIVE attributes and typed-routed into
 * product_attribute_values by ProductAttributeValueWriter) and delete. The
 * controller stays thin; this Service is the single authority.
 *
 * @see ProductAttributeValueWriter
 */
class ProductService
{
    /**
     * Relations eager-loaded on every returned model, so ProductResource
     * never N+1s while hydrating the category summary and the typed
     * attribute values.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = ['category', 'attributeValues.attribute', 'attributeValues.option'];

    public function __construct(
        private readonly ProductCategoryService $categoryService,
        private readonly ProductAttributeValueWriter $valueWriter,
    ) {}

    public function create(CreateProductData $data): Product
    {
        $category = ProductCategory::findOrFail($data->categoryId);
        $effective = $this->categoryService->effectiveAttributes($category);
        $submitted = $data->attributes ?? [];

        $this->valueWriter->guardValues($effective, $submitted);

        return DB::transaction(function () use ($data, $effective, $submitted): Product {
            /** @var Product $product */
            $product = Product::create([
                'name' => $data->name,
                'description' => $data->description,
                'cost' => $data->cost,
                'price' => $data->price,
                'category_id' => $data->categoryId,
            ]);

            $this->valueWriter->replaceValues($product, $effective, $submitted);

            return $product->fresh(self::HYDRATED_RELATIONS);
        });
    }

    public function update(Product $product, UpdateProductData $data): Product
    {
        $categoryId = $data->hasCategoryId() ? $data->categoryId : $product->category_id;
        $category = ProductCategory::findOrFail($categoryId);
        $effective = $this->categoryService->effectiveAttributes($category);
        $categoryChanged = $data->hasCategoryId() && $data->categoryId !== $product->category_id;

        if ($data->hasAttributes()) {
            $this->valueWriter->guardValues($effective, $data->attributes);
        } elseif ($categoryChanged) {
            // Category changed but no explicit `attributes` payload: the new
            // category's required set is enforced against the CURRENT values
            // (AC-017) before anything is pruned/persisted.
            $this->valueWriter->guardValues($effective, $this->valueWriter->currentValuesAsSubmission($product, $effective));
        }

        return DB::transaction(function () use ($product, $data, $effective, $categoryChanged): Product {
            $attributes = $data->submittedAttributes();

            if ($attributes !== []) {
                $product->update($attributes);
            }

            if ($data->hasAttributes()) {
                $this->valueWriter->replaceValues($product, $effective, $data->attributes);
            } elseif ($categoryChanged) {
                $this->valueWriter->pruneIrrelevantValues($product, $effective);
            }

            return $product->fresh(self::HYDRATED_RELATIONS);
        });
    }

    /**
     * The product_attribute_values rows cascade on delete (migration FK), so
     * no explicit cleanup is needed here.
     */
    public function delete(Product $product): void
    {
        $product->delete();
    }
}
