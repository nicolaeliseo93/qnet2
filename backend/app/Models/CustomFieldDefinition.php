<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CustomFieldDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Universal custom field metadata (spec 0021): a typed, dynamic field
 * scoped to a custom-fieldable entity_type (a domain already registered in
 * TableRegistry + AuthorizationRegistry, e.g. "companies"). The actual
 * values live in `custom_field_values` (JSON-per-entity), not here.
 */
#[Fillable([
    'entity_type', 'key', 'type', 'label', 'description', 'help_text', 'placeholder',
    'icon', 'group', 'tab', 'sort_order', 'default_value', 'config', 'validation',
    'relation_target', 'is_indexed', 'is_active',
])]
class CustomFieldDefinition extends BaseModel
{
    /** @use HasFactory<CustomFieldDefinitionFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_value' => 'array',
            'config' => 'array',
            'validation' => 'array',
            'relation_target' => 'array',
            'is_indexed' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The discrete value list, ENUM-typed definitions only.
     */
    public function options(): HasMany
    {
        return $this->hasMany(CustomFieldOption::class, 'definition_id')->orderBy('sort_order');
    }
}
