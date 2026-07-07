<?php

namespace App\Models;

use App\Enums\AttributeType;
use App\Models\Abstracts\BaseModel;
use Database\Factories\ProductAttributeValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One typed EAV row for a product's dynamic attribute (spec 0017): the value
 * lives in whichever value_* column matches the owning attribute's
 * data_type (or `option_id` for ENUM). Managed exclusively through
 * ProductService as a nested full-replace on the owning product — never
 * audited independently.
 */
#[Fillable(['product_id', 'attribute_id', 'value_string', 'value_integer', 'value_decimal', 'value_boolean', 'option_id'])]
class ProductAttributeValue extends BaseModel
{
    /** @use HasFactory<ProductAttributeValueFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_decimal' => 'decimal:6',
            'value_boolean' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(AttributeOption::class, 'option_id');
    }

    /**
     * The single typed value, resolved from whichever value_* column matches
     * the owning attribute's data_type — the read-side counterpart of
     * ProductService's typed-column routing on write. ENUM resolves to the
     * option's `value` (the option's own identity, not option_id).
     */
    public function getValueAttribute(): string|int|float|bool|null
    {
        return match ($this->attribute?->data_type) {
            AttributeType::Integer => $this->value_integer,
            AttributeType::Decimal => $this->value_decimal !== null ? (float) $this->value_decimal : null,
            AttributeType::Boolean => $this->value_boolean,
            AttributeType::Enum => $this->option?->value,
            default => $this->value_string,
        };
    }
}
