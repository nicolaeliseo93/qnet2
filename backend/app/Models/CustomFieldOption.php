<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CustomFieldOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single discrete option of an ENUM-typed custom field definition (spec
 * 0021). Managed as a nested full-replace on the owning definition — mirrors
 * AttributeOption for the product EAV.
 */
#[Fillable(['definition_id', 'value', 'label', 'color', 'icon', 'sort_order', 'is_default'])]
class CustomFieldOption extends BaseModel
{
    /** @use HasFactory<CustomFieldOptionFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }
}
