<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PipelineStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Project status lookup entity (spec 0023): a full-CRUD classification
 * (name/color/sort_order) shared by Projects and Campaigns. Named
 * `PipelineStatus`/`pipeline_statuses` (not `State`/`states`) because that name
 * is already taken by the geo entity ("Regione").
 */
#[Fillable(['name', 'color', 'sort_order'])]
class PipelineStatus extends BaseModel
{
    /** @use HasFactory<PipelineStatusFactory> */
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
     * The projects classified under this status. `pipeline_status_id` is
     * restrictOnDelete (migration, BR-4): deleting a status referenced by a
     * project is rejected at the schema layer too.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * The standalone campaigns classified under this status (own, not
     * derived from a linked project — BR-2). Same restrictOnDelete guard.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
