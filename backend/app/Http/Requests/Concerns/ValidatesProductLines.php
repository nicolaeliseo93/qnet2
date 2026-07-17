<?php

namespace App\Http\Requests\Concerns;

use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for the `product_lines` payload of the opportunity write
 * endpoints (spec 0040, amendment rev.3): a to-many collection of
 * {business_function_id, product_category_id} rows, REPLACING the former
 * single scalar columns. An opportunity must ALWAYS carry at least one row
 * (user directive 2026-07-17): create REQUIRES a non-empty collection, and
 * update — a full-replace sync — may omit `product_lines` (partial PATCH,
 * untouched) but may NOT clear it to `[]`. The base per-row rules live in
 * productLinesRules(); the cross-row invariants (no duplicate pair, the
 * category's EFFECTIVE business function must match the row's own) run in
 * validateProductLines(), called from each request's own withValidator().
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesProductLines
{
    /**
     * @param  bool  $required  create passes true (collection mandatory);
     *                          update passes false (`sometimes`, but `min:1`
     *                          still bars a clear-to-empty).
     * @return array<string, array<int, mixed>>
     */
    protected function productLinesRules(bool $required): array
    {
        return [
            'product_lines' => $required
                ? ['required', 'array', 'min:1']
                : ['sometimes', 'array', 'min:1'],
            'product_lines.*.business_function_id' => ['required', 'integer', Rule::exists('business_functions', 'id')],
            'product_lines.*.product_category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')],
        ];
    }

    /**
     * Cross-row rules: (business_function_id, product_category_id) may not
     * repeat, and each row's category must belong to that EXACT business
     * function once inheritance is resolved (CategoryHierarchy).
     */
    protected function validateProductLines(Validator $validator): void
    {
        $lines = $this->input('product_lines');

        if (! is_array($lines)) {
            return;
        }

        $hierarchy = app(CategoryHierarchy::class);
        $seenPairs = [];

        foreach ($lines as $index => $line) {
            if (! $this->isWellFormedLine($line)) {
                continue;
            }

            $businessFunctionId = (int) $line['business_function_id'];
            $productCategoryId = (int) $line['product_category_id'];
            $pairKey = "{$businessFunctionId}:{$productCategoryId}";

            if (isset($seenPairs[$pairKey])) {
                $validator->errors()->add("product_lines.{$index}.product_category_id", 'This business function / product category pair is already present.');

                continue;
            }

            $seenPairs[$pairKey] = true;

            $this->assertCategoryMatchesBusinessFunction($validator, $index, $hierarchy, $productCategoryId, $businessFunctionId);
        }
    }

    private function isWellFormedLine(mixed $line): bool
    {
        return is_array($line) && isset($line['business_function_id'], $line['product_category_id']);
    }

    private function assertCategoryMatchesBusinessFunction(
        Validator $validator,
        int $index,
        CategoryHierarchy $hierarchy,
        int $productCategoryId,
        int $businessFunctionId,
    ): void {
        $category = ProductCategory::find($productCategoryId);

        if ($category === null) {
            // The `exists` rule on product_category_id already reports this.
            return;
        }

        $effective = $hierarchy->effectiveBusinessFunction($category);

        if ($effective === null || $effective['id'] !== $businessFunctionId) {
            $validator->errors()->add("product_lines.{$index}.business_function_id", 'This product category does not belong to the selected business function.');
        }
    }
}
