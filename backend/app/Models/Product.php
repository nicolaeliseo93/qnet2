<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product (spec 0017): generic fields only (name/description/cost/price/
 * category/product_type). No longer carries category-driven attribute
 * values — the `attributes` catalogue (Attribute/ProductCategory) stays a
 * reusable template, decoupled from any per-product value storage.
 */
#[Fillable(['name', 'description', 'cost', 'price', 'category_id', 'product_type'])]
class Product extends BaseModel
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'price' => 'decimal:2',
            'product_type' => ProductType::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
}
