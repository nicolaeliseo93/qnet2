<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's saved filter state for one table domain (ADR-0004, sibling of
 * UserTablePreference).
 *
 * `filters` is the AG Grid filterModel the user last applied (keyed by column
 * id). It is restricted to the definition's filterable columns server-side before
 * being persisted, and re-validated again at query time (TableRowsRequest), so it
 * is never a SQL sink — it is only replayed into the grid to survive a reload.
 *
 * `advanced_filters` (spec 0032) is its sibling for the second-level,
 * backend-driven advanced-filter panel: keyed by advanced-filter name,
 * restricted to the definition's advancedFilterableIds() the same way.
 *
 * Intentionally NOT activity-logged and NOT backed by a Policy — see ADR-0004:
 *  - Activity log: high-churn per-user UI state with no audit value.
 *  - Policy: self-scoped by construction (the endpoints always key on
 *    auth()->id(), never a client-supplied user_id) and gated by the target
 *    table's own viewAny — there is no cross-user access path to police.
 *
 * No sensitive data is ever stored here.
 */
class UserTableFilter extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'domain', 'filters', 'advanced_filters'];

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
            'advanced_filters' => 'array',
        ];
    }
}
