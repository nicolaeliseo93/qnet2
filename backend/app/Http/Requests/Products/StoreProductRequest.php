<?php

namespace App\Http\Requests\Products;

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/products (spec 0017). Only the
 * generic fields (name/description/cost/price/category_id) exist on a
 * product — the category-driven `attributes` catalogue stays a reusable
 * template (Attribute/ProductCategory), never coupled to a product's own
 * values.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Product::class)). EnforcesFieldPermissions
 * (spec 0004) rejects any submitted field the actor cannot edit (create
 * context, model = null).
 */
class StoreProductRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ProductPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'cost' => ['required', 'numeric'],
            'price' => ['required', 'numeric'],
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'product_type' => ['required', Rule::enum(ProductType::class)],
            'vat_rate_id' => ['nullable', 'integer', 'exists:vat_rates,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:registries,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'products';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateProductData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateProductData::fromValidated($validated);
    }
}
