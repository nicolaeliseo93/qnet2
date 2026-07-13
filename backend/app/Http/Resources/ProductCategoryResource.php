<?php

namespace App\Http\Resources;

use App\Models\Attribute;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductCategory
 */
class ProductCategoryResource extends JsonResource
{
    /**
     * Own attribute assignments only — inherited ones are attached via
     * `additional(['inherited_attributes' => ...])` by the controller
     * (ProductCategoryService::inheritedAttributes), never merged here.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'parent' => $this->parent !== null ? ['id' => $this->parent->id, 'name' => $this->parent->name] : null,
            'inherits_attributes' => (bool) $this->inherits_attributes,
            'description' => $this->description,
            'attributes' => $this->attributes->map(fn (Attribute $attribute): array => [
                'attribute_id' => $attribute->id,
                'code' => $attribute->code,
                'name' => $attribute->name,
                'type' => $attribute->type,
                'is_required' => (bool) $attribute->pivot->is_required,
                'sort_order' => (int) $attribute->pivot->sort_order,
            ])->all(),
            'created_at' => $this->created_at,
        ];
    }
}
