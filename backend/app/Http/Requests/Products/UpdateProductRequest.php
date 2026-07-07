<?php

namespace App\Http\Requests\Products;

use App\DataObjects\Products\UpdateProductData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PUT/PATCH /api/products/{product} (spec 0017).
 * Generic fields are `sometimes`; `attributes`, when submitted, is a
 * full-replace of the dynamic values, validated against the (possibly new)
 * category's effective attributes by ProductService — see
 * StoreProductRequest's docblock for why that cross-field validation is not
 * duplicated here. Authorization is intentionally NOT handled here (it stays
 * in the controller via authorize('update', $product)). EnforcesFieldPermissions
 * (spec 0004) rejects any submitted GENERIC field the actor cannot edit on
 * this specific model.
 */
class UpdateProductRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'cost' => ['sometimes', 'nullable', 'numeric'],
            'price' => ['sometimes', 'nullable', 'numeric'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:product_categories,id'],
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
        /** @var Product $product */
        $product = $this->route('product');

        return $product;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateProductData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateProductData::fromValidated($validated);
    }
}
