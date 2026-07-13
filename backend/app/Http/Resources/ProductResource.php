<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * $effectiveBusinessFunction is resolved by ProductService (spec 0023)
     * and passed in explicitly — never computed here — because it requires
     * CategoryHierarchy's ancestor walk, which stays out of the Resource
     * layer (Controller thin -> Service authoritative -> Resource pure
     * output shape).
     *
     * @param  array{id: int, name: string}|null  $effectiveBusinessFunction
     */
    public function __construct(Product $resource, private readonly ?array $effectiveBusinessFunction = null)
    {
        parent::__construct($resource);
    }

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
            // Read-only, derived from the category (spec 0023): never
            // writable via POST/PATCH (not in $fillable, no FormRequest rule).
            'business_function' => $this->effectiveBusinessFunction,
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
