<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product category tree node (spec 0017): unlimited-depth parent/child
 * hierarchy. A category's EFFECTIVE attributes are its own `attributes()`
 * assignments UNION every ancestor's (see ProductCategoryService).
 */
#[Fillable(['name', 'parent_id', 'inherits_attributes', 'description'])]
class ProductCategory extends BaseModel
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inherits_attributes' => 'boolean',
            // Spec 0013 — external data migration: the source system's id for a
            // migrated category, guarded (not in #[Fillable]) so it is only ever
            // set by property assignment post-create. Also the remap key for the
            // self-referential `parent_id` (child → parent via old_id).
            'old_id' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Attributes assigned directly to THIS category (own assignments — not
     * including attributes only inherited from an ancestor).
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_category', 'category_id', 'attribute_id')
            ->withPivot(['is_required', 'sort_order'])
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
