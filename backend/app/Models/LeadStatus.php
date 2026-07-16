<?php

namespace App\Models;

use App\Enums\StatusGroup;
use App\Enums\StatusSystemKey;
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
 *
 * spec 0039: `system_key` (nullable, the THREE mandatory "Nuovo"/"Chiuso con
 * successo"/"Scartato" rows — pivot, D-2) is DELIBERATELY absent from
 * #[Fillable] — never mass-assignable, written only by the system-status
 * migration and App\Services\Statuses\SystemStatusGuard/StatusOrderManager.
 * `sort_order` stays fillable (D-5): it becomes server-managed
 * (StatusOrderManager places/reorders it), not user-fillable at the
 * FormRequest layer, but the Service still assigns it via mass-assignment
 * internally. `group` (pivot) is the fixed 3-value classification
 * (App\Enums\StatusGroup) — replaces the earlier "status groups" lookup FK.
 */
#[Fillable(['name', 'color', 'sort_order', 'group'])]
class LeadStatus extends BaseModel
{
    /** @use HasFactory<LeadStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The system rows that pin to the tail of the sort_order sequence
     * (StatusOrderManager, spec 0039 D-5), in the order they must appear:
     * "Chiuso con successo" then "Scartato" — the latter is ALWAYS last.
     *
     * @var array<int, StatusSystemKey>
     */
    public const array SYSTEM_TAIL_KEYS = [StatusSystemKey::Won, StatusSystemKey::Discarded];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'group' => StatusGroup::class,
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
     * Whether this is one of the three mandatory system rows ("Nuovo"/
     * "Chiuso con successo"/"Scartato", spec 0039 D-2) rather than a
     * custom, user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
