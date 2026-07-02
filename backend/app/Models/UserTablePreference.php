<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's saved column layout for one table domain (ADR-0004).
 *
 * `preferences` is a SPARSE delta over the domain's PHP default schema (only the
 * properties the user changed: visible / width / order), computed server-side so
 * the PHP TableDefinition remains the single source of truth.
 *
 * Intentionally NOT activity-logged (no LogsModelActivity) and NOT backed by a
 * Policy — see ADR-0004:
 *  - Activity log: high-churn per-user UI state with no audit value; logging
 *    every resize/reorder would be pure noise. Explicit, documented exemption.
 *  - Policy: the resource is self-scoped by construction (the endpoints always
 *    key on auth()->id(), never a client-supplied user_id) and gated by the
 *    target table's own viewAny — there is no cross-user access path to police.
 *
 * No sensitive data is ever stored here.
 */
class UserTablePreference extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'domain', 'preferences'];

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
            'preferences' => 'array',
        ];
    }
}
