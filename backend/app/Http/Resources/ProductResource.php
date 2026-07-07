<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'cost' => $this->cost,
            'price' => $this->price,
            'category_id' => $this->category_id,
            'category' => $this->categorySummary($this->category),
            'product_type' => $this->product_type,
            'attributes' => $this->attributeValues->map(fn (ProductAttributeValue $value): array => [
                'attribute_id' => $value->attribute_id,
                'code' => $value->attribute->code,
                'name' => $value->attribute->name,
                'data_type' => $value->attribute->data_type,
                'value' => $value->value,
                'option_id' => $value->option_id,
            ])->all(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function categorySummary(?ProductCategory $category): ?array
    {
        if ($category === null) {
            return null;
        }

        return ['id' => $category->id, 'name' => $category->name];
    }
}
