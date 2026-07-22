<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Endpoint-focused scope guard for the request-management work panel (spec
 * 0049, D-3): an actor may reach a given opportunity's panel when they hold
 * `request-management.viewAll` (sees every opportunity) OR are that
 * opportunity's GA2 "Operatore" — the Account Manager at pivot position
 * `Opportunity::OPERATOR_MANAGER_POSITION`; otherwise 403.
 *
 * Mirrors the SAME GA2 scoping rule the request-management TableDefinition's
 * baseQuery() applies to the list (LeadImportsTableDefinition precedent:
 * authorize first, never fail-open) — kept as a SEPARATE guard here since this
 * class is endpoint-focused (show/update), not query-building.
 */
final class RequestManagementScope
{
    /**
     * @throws HttpException 403 when $user is neither the GA2 operator of
     *                       $opportunity nor holds the viewAll ability
     */
    public function assertInScope(User $user, Opportunity $opportunity): void
    {
        if ($user->can('request-management.viewAll')) {
            return;
        }

        if ($this->isOperatorOf($user, $opportunity)) {
            return;
        }

        abort(403);
    }

    /**
     * Whether $user is $opportunity's GA2 "Operatore" (the Account Manager at
     * pivot position Opportunity::OPERATOR_MANAGER_POSITION) — the D-3 scoping
     * rule, isolated so it can be asserted directly in tests without
     * triggering the abort side effect.
     */
    public function isOperatorOf(User $user, Opportunity $opportunity): bool
    {
        return $opportunity->managers()
            ->where('users.id', $user->id)
            ->wherePivot('position', Opportunity::OPERATOR_MANAGER_POSITION)
            ->exists();
    }
}
