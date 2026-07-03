<?php

namespace App\Models;

use App\Enums\FilterViewVisibility;
use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's named, saved AG Grid filter set for one table domain (spec 0007),
 * either private (owner only) or shared (every user who can view the table).
 *
 * Unlike UserTableFilter (the single "currently applied" filter state), a user
 * may save MANY named views per domain. Shared views are a real cross-user
 * access surface, so this model IS backed by a Policy
 * (TableFilterViewPolicy) — the one difference from its sibling.
 *
 * `filters` is restricted to the definition's filterable columns on every
 * write (TableFilterViewRequest) and re-filtered on every read
 * (TableFilterViewService), so it is never a SQL sink and can never widen the
 * SSRM filter allow-list.
 */
class TableFilterView extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'domain', 'name', 'filters', 'visibility'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'visibility' => FilterViewVisibility::class,
        ];
    }
}
