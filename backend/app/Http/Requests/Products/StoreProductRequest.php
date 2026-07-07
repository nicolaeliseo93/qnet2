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
 * generic fields (name/description/cost/price/category_id) are structurally
 * validated here; the dynamic `attributes` cross-field validation (must
 * belong to the category's effective attributes, value coherent with the
 * attribute's data_type, required attributes present) is enforced by
 * ProductService — it needs the category's effective attribute catalogue,
 * which this FormRequest has no business resolving.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Product::class)). EnforcesFieldPermissions
 * (spec 0004) rejects any submitted GENERIC field the actor cannot edit
 * (create context, model = null) — dynamic attributes are authorized at the
 * resource level (products.update), never per-field (spec 0017 decision).
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
            'attributes' => ['sometimes', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id', 'distinct'],
            'attributes.*.value' => ['nullable'],
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
