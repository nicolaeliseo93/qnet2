<?php

namespace App\Services;

use App\DataObjects\Products\CreateProductData;
use App\DataObjects\Products\UpdateProductData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Product;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;

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
    private const array HYDRATED_RELATIONS = ['category', 'vatRate', 'supplier'];

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
            'vat_rate_id' => $data->vatRateId,
            'supplier_id' => $data->supplierId,
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

    /**
     * Minimal, searchable, paginated product list for the for-select standard
     * (ADR 0011), mirroring VatRateService::forSelect. `category_ids` (user
     * directive 2026-07-22) scopes the page to the products of those exact
     * categories — how the "prodotti di interesse" picker stays aligned with
     * the opportunity's product lines; absent, the whole catalogue is
     * searchable (the picker's explicit "unlock").
     *
     * The category is projected as `meta.category` so the operator can tell
     * two same-named products apart, and so the unlocked picker shows what a
     * cross-category pick would add to the opportunity's product lines.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = Product::query()->select(['id', 'name', 'category_id']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        if ($query->hasCategoryIds()) {
            $base->whereIn('category_id', $query->categoryIds);
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Product> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);
        $items->load('category:id,name');

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass BOTH the search and
     * the category scope — a product already selected must keep its label
     * even once it falls outside the current filter. Total is unaffected.
     *
     * @param  Collection<int, Product>  $page
     * @return Collection<int, Product>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Product> $hydrated */
        $hydrated = Product::query()
            ->select(['id', 'name', 'category_id'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
