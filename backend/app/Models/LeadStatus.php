<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\LeadStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lead status lookup entity (spec 0029): a full-CRUD classification
 * (name/color/sort_order) describing a Lead's working state. `name` is
 * unique (BR-2/D-4), unlike PipelineStatus.
 */
#[Fillable(['name', 'color', 'sort_order'])]
class LeadStatus extends BaseModel
{
    /** @use HasFactory<LeadStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
        ];
    }

    /**
     * The leads classified under this status. `lead_status_id` is
     * restrictOnDelete (migration, BR-3): deleting a status referenced by a
     * lead is rejected at the schema layer too.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
