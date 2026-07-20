<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;

/**
 * App-wide singleton: the ordered mass-import plan (spec 0046). `sources` is an
 * ordered list of `{source, enabled}` — which migration sources (spec 0013) the
 * mass import runs and in what order. Read and reconciled against the live
 * registry through App\Services\MigrationPlanService (the default, when no row
 * exists, is App\Migrations\MigrationOrder::PHASES flattened, all enabled).
 */
class MigrationPlan extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['sources'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sources' => 'array',
        ];
    }
}
