<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared classification-coherence check (spec 0023 REV), reused by the four
 * Project/Campaign FormRequests. A plain `exists:` rule only confirms each FK
 * points at a real row; this additionally confirms the selected product
 * category actually belongs to the selected business function — its EFFECTIVE
 * business function (own, or the first one found walking `parent_id` toward the
 * root: the SAME notion the product-categories/for-select filter scopes by, so
 * a category the UI would never offer for that function is rejected here too).
 *
 * A violation is a 422 on `product_category_id`. The check is skipped when
 * either id is null (the caller resolves the EFFECTIVE pair — submitted value,
 * or the row's current one in update — and a standalone-only pair for
 * campaigns, so a linked campaign whose classification is derived never
 * reaches this).
 */
trait ValidatesProductCategoryBusinessFunction
{
    protected function validateProductCategoryBusinessFunction(
        Validator $validator,
        ?int $businessFunctionId,
        ?int $productCategoryId,
    ): void {
        if ($businessFunctionId === null || $productCategoryId === null) {
            return;
        }

        $category = ProductCategory::query()->find($productCategoryId);

        // A non-existent category is already a 422 from the `exists:` rule.
        if ($category === null) {
            return;
        }

        $effective = app(CategoryHierarchy::class)->effectiveBusinessFunction($category);

        if (($effective['id'] ?? null) !== $businessFunctionId) {
            $validator->errors()->add(
                'product_category_id',
                'The selected product category does not belong to the selected business function.',
            );
        }
    }
}
