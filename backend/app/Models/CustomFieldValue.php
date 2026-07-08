<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\CustomFieldValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * JSON-per-entity custom field values (spec 0021): one row per
 * (entity_type, entity_id), `values` a map {key: typed value}. Written
 * exclusively through the HasCustomFields trait's saved/deleting observers
 * on the owning model — never audited independently (high write volume,
 * one row per record per save).
 */
#[Fillable(['entity_type', 'entity_id', 'values'])]
class CustomFieldValue extends BaseModel
{
    /** @use HasFactory<CustomFieldValueFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'values' => 'array',
            'entity_id' => 'integer',
        ];
    }
}
