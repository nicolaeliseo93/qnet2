<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\AttributeOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single discrete option of an ENUM-typed Attribute (spec 0017). Managed
 * exclusively through AttributeService as a nested full-replace on the
 * owning attribute — never audited independently.
 */
#[Fillable(['attribute_id', 'value', 'label', 'sort_order'])]
class AttributeOption extends BaseModel
{
    /** @use HasFactory<AttributeOptionFactory> */
    use HasFactory;

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
