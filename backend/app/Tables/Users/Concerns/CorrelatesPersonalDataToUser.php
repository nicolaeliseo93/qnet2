<?php

namespace App\Tables\Users\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared correlation for a `personal_data`-based subquery used as an ORDER BY
 * scalar: bind it to the outer `users` row and limit it to a single result.
 *
 * Shared by the users-domain derived-column collaborators (geo, personal
 * data) so each can build its own correlated sort subquery without
 * duplicating the join condition. `personable_type` uses the morph alias
 * (enforced morphMap), never the FQCN.
 */
trait CorrelatesPersonalDataToUser
{
    /**
     * @param  Builder<Model>  $subquery
     * @return Builder<Model>
     */
    private function correlateToUser(Builder $subquery): Builder
    {
        return $subquery
            ->whereColumn('personal_data.personable_id', 'users.id')
            ->where('personal_data.personable_type', (new User)->getMorphClass())
            ->limit(1);
    }
}
