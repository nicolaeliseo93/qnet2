<?php

namespace App\Policies;

use App\Models\TableFilterView;
use App\Models\User;

/**
 * Owner-only authorization for a saved TableFilterView (spec 0007).
 *
 * List/create are gated by the table definition's own viewAny (enforced in
 * TableFilterViewController, same as every tables/{domain} endpoint) — this
 * Policy exists ONLY for update/delete, because a shared view is a real
 * cross-user access surface.
 *
 * Deliberately NOT extending BasePolicy (Spatie permission-backed): ownership,
 * not a permission string, is the rule here. The global super-admin bypass
 * (Gate::before in AppServiceProvider) already grants a super-admin actor
 * every ability, so it is NOT duplicated here.
 */
class TableFilterViewPolicy
{
    public function update(User $user, TableFilterView $view): bool
    {
        return $view->user_id === $user->id;
    }

    public function delete(User $user, TableFilterView $view): bool
    {
        return $view->user_id === $user->id;
    }
}
