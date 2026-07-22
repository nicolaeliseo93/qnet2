<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\ActivityLog\Contracts\ActivityLogAuthorizer;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-gate of the `request-management` activity resource (spec 0049 D-7,
 * amended): the record IS an Opportunity (D-1) but this module authorizes
 * through its OWN permission set, never `opportunities.*` — the default
 * PolicyActivityLogAuthorizer would have resolved OpportunityPolicy, which is
 * exactly why the resource key stayed unregistered until now.
 *
 * The rule is the work panel's rule verbatim (no separate story invented
 * here): `request-management.viewActivity` for the surface, plus the same GA2
 * operator/`viewAll` record boundary RequestManagementScope enforces on
 * show/update — an actor never reads the history of a request they cannot
 * open.
 *
 * Lives in this module's OWN namespace alongside RequestManagementNotable
 * (the notes equivalent), referenced as a pure class-string in
 * config/activity-log.php: app/ActivityLog/ stays agnostic.
 */
final class RequestManagementActivityAuthorizer implements ActivityLogAuthorizer
{
    public function __construct(private readonly RequestManagementScope $scope) {}

    public function authorize(User $user, Model $record): void
    {
        abort_unless($record instanceof Opportunity, 403);
        abort_unless($user->can('request-management.viewActivity'), 403);

        $this->scope->assertInScope($user, $record);
    }
}
