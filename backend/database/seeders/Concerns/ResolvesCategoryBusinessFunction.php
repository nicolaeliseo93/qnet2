<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;

/**
 * Builds coherent (product_category_id, business_function_id) pairs for the
 * demo seeders (spec 0023 REV): the business function is DERIVED from each
 * category's EFFECTIVE one (own or inherited), so the demo data never pairs a
 * category with a mismatched function — the same invariant the FormRequests now
 * reject. Categories with no effective business function are dropped (they
 * cannot form a valid required pair on a standalone Project/Campaign).
 */
trait ResolvesCategoryBusinessFunction
{
    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @return Collection<int, array{product_category_id: int, business_function_id: int}>
     */
    protected function coherentClassificationPairs(Collection $categories): Collection
    {
        $summaries = app(CategoryHierarchy::class)->effectiveBusinessFunctionSummaries();

        return $categories
            ->map(static fn (ProductCategory $category): array => [
                'product_category_id' => $category->id,
                'business_function_id' => $summaries[$category->id]['id'] ?? null,
            ])
            ->filter(static fn (array $pair): bool => $pair['business_function_id'] !== null)
            ->values();
    }
}
