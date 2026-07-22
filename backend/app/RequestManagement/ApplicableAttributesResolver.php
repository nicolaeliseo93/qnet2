<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;

/**
 * The opportunity-level "applicable attributes" set (spec 0049, D-4/AC-022):
 * the UNION, deduped by `code`, of the EFFECTIVE (own+inherited) attributes
 * of every DISTINCT product category across the opportunity's product lines
 * (App\Services\ProductCategories\CategoryHierarchy::effectiveAttributes()).
 * When the same `code` is required by at least one line's category, the
 * merged descriptor is required (the strictest requirement wins); its
 * position stays where it FIRST appeared while merging, then the whole set
 * is reordered by sort_order/code for a stable, deterministic response.
 *
 * N+1-free: `productLines.productCategory` is eager-loaded once and
 * categories are deduped by id BEFORE calling effectiveAttributes() — a
 * category shared by several product lines is resolved only once, never
 * once per line.
 */
final class ApplicableAttributesResolver
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * @return Collection<int, ApplicableAttribute>
     */
    public function resolve(Opportunity $opportunity): Collection
    {
        // Step 1: distinct product categories across all product lines
        $categories = $this->distinctCategories($opportunity);

        if ($categories->isEmpty()) {
            return collect();
        }

        // Step 2: union + dedup by code (required propagates across categories)
        $merged = $this->mergeByCode($categories);

        // Step 3: stable order — sort_order then code
        return $merged->values()
            ->sort(fn (ApplicableAttribute $a, ApplicableAttribute $b): int => [$a->sortOrder, $a->code] <=> [$b->sortOrder, $b->code])
            ->values();
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    private function distinctCategories(Opportunity $opportunity): Collection
    {
        $opportunity->loadMissing('productLines.productCategory');

        return $opportunity->productLines
            ->pluck('productCategory')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @return Collection<string, ApplicableAttribute> keyed by `code`
     */
    private function mergeByCode(Collection $categories): Collection
    {
        $merged = collect();

        foreach ($categories as $category) {
            foreach ($this->hierarchy->effectiveAttributes($category) as $row) {
                $this->mergeOne($merged, ApplicableAttribute::fromEffectiveAttributeRow($row));
            }
        }

        return $merged;
    }

    /**
     * @param  Collection<string, ApplicableAttribute>  $merged
     */
    private function mergeOne(Collection $merged, ApplicableAttribute $attribute): void
    {
        $existing = $merged->get($attribute->code);

        if ($existing === null) {
            $merged->put($attribute->code, $attribute);

            return;
        }

        if ($attribute->isRequired && ! $existing->isRequired) {
            $merged->put($attribute->code, $existing->withRequired(true));
        }
    }
}
