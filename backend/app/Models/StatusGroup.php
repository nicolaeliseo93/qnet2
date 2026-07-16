<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\StatusGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status group lookup entity (spec 0039, D-6): a full-CRUD classification
 * (name/color/sort_order) GLOBALLY shared by both status configurators
 * (pipeline_statuses, lead_statuses). `name` is unique, mirroring
 * LeadStatus rather than PipelineStatus (D-6 reuses the lead-statuses
 * template). Unlike the two status tables, `sort_order` here stays a plain
 * manual integer — groups are not drag & drop reorderable.
 */
#[Fillable(['name', 'color', 'sort_order'])]
class StatusGroup extends BaseModel
{
    /** @use HasFactory<StatusGroupFactory> */
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
     * The pipeline statuses classified under this group. `status_group_id`
     * is restrictOnDelete (migration): deleting a group referenced by a
     * status is rejected at the schema layer too (defense in depth for the
     * Service-level 409 guard).
     */
    public function pipelineStatuses(): HasMany
    {
        return $this->hasMany(PipelineStatus::class);
    }

    /**
     * The lead statuses classified under this group. Same restrictOnDelete
     * guard as pipelineStatuses().
     */
    public function leadStatuses(): HasMany
    {
        return $this->hasMany(LeadStatus::class);
    }
}
