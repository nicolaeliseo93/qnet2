<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\LeadStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lead status lookup entity (spec 0029): a full-CRUD classification
 * (name/color/sort_order) describing a Lead's working state. `name` is
 * unique (BR-2/D-4), unlike PipelineStatus.
 *
 * spec 0039: `system_key` (nullable, the two mandatory "Nuovo"/"Chiuso" rows)
 * is DELIBERATELY absent from #[Fillable] — never mass-assignable, written
 * only by the system-status migration and App\Services\Statuses\
 * SystemStatusGuard/StatusOrderManager. `sort_order` stays fillable (D-5):
 * it becomes server-managed (StatusOrderManager places/reorders it), not
 * user-fillable at the FormRequest layer, but the Service still assigns it
 * via mass-assignment internally.
 */
#[Fillable(['name', 'color', 'sort_order', 'status_group_id'])]
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
            'status_group_id' => 'int',
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

    /**
     * The optional classification group (spec 0039, D-6) — nullable, custom
     * rows only in practice (a system row's group is fixed at migration
     * time, D-2, and never reassigned).
     */
    public function statusGroup(): BelongsTo
    {
        return $this->belongsTo(StatusGroup::class);
    }

    /**
     * Whether this is one of the two mandatory system rows ("Nuovo"/
     * "Chiuso", spec 0039 D-2) rather than a custom, user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
