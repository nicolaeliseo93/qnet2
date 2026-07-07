<?php

namespace App\Http\Requests\ProductCategories;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/product-categories (spec 0017).
 *
 * A cycle is structurally impossible on create (the category has no id yet),
 * so no cycle guard is needed here (unlike UpdateProductCategoryRequest's
 * companion Service-level guard). Authorization is intentionally NOT handled
 * here (it stays in the controller via authorize('create',
 * ProductCategory::class)). EnforcesFieldPermissions (spec 0004) additionally
 * rejects any submitted field the actor cannot edit (create context, model =
 * null).
 */
class StoreProductCategoryRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ProductCategoryPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'inherits_attributes' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'attributes' => ['sometimes', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id', 'distinct'],
            'attributes.*.is_required' => ['sometimes', 'boolean'],
            'attributes.*.sort_order' => ['sometimes', 'integer'],
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
        return 'product-categories';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateProductCategoryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateProductCategoryData::fromValidated($validated);
    }
}
