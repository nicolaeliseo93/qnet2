<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product (spec 0017): generic fields only (name/description/cost/price/
 * category). Its dynamic, category-driven attribute values live in
 * `attributeValues()` (product_attribute_values, typed columns).
 */
#[Fillable(['name', 'description', 'cost', 'price', 'category_id'])]
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
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * The product's dynamic attribute values (EAV rows), eager-loaded with
     * their attribute + option so ProductResource never N+1s.
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
